import argparse
import json
import lzma
import os
import re
import struct
import sys
import zlib

SHEET_PIXEL_SIZE = 384
SPRITE_SIZE_BY_LAYOUT = {0: (32, 32), 1: (32, 64), 2: (64, 32), 3: (64, 64)}
CIP_SIGNATURE_LENGTH = 5
APPEARANCES_OBJECT_FIELD = 1
APPEARANCE_ID_FIELD = 1
APPEARANCE_FRAME_GROUP_FIELD = 2
FRAME_GROUP_SPRITE_INFO_FIELD = 3
SPRITE_INFO_SPRITE_ID_FIELD = 5


def read_varint(buffer, position):
    result = 0
    shift = 0
    while True:
        byte = buffer[position]
        position += 1
        result |= (byte & 0x7F) << shift
        if not byte & 0x80:
            return result, position
        shift += 7


def iterate_fields(buffer, start=0, end=None):
    position = start
    end = len(buffer) if end is None else end
    while position < end:
        key, position = read_varint(buffer, position)
        field_number = key >> 3
        wire_type = key & 0x07
        if wire_type == 0:
            value, position = read_varint(buffer, position)
            yield field_number, wire_type, value
        elif wire_type == 2:
            length, position = read_varint(buffer, position)
            yield field_number, wire_type, (position, position + length)
            position += length
        elif wire_type == 5:
            yield field_number, wire_type, buffer[position:position + 4]
            position += 4
        elif wire_type == 1:
            yield field_number, wire_type, buffer[position:position + 8]
            position += 8
        else:
            raise ValueError(f"unsupported wire type {wire_type}")


def read_first_sprite_id(buffer, sprite_info_range):
    for field_number, wire_type, value in iterate_fields(buffer, *sprite_info_range):
        if field_number != SPRITE_INFO_SPRITE_ID_FIELD:
            continue
        if wire_type == 0:
            return value
        first_sprite_id, _ = read_varint(buffer, value[0])
        return first_sprite_id
    return None


def read_object_first_sprite(buffer, object_range):
    object_id = None
    first_sprite_id = None
    for field_number, wire_type, value in iterate_fields(buffer, *object_range):
        if field_number == APPEARANCE_ID_FIELD and wire_type == 0:
            object_id = value
        elif field_number == APPEARANCE_FRAME_GROUP_FIELD and wire_type == 2 and first_sprite_id is None:
            for group_field, group_wire_type, group_value in iterate_fields(buffer, *value):
                if group_field == FRAME_GROUP_SPRITE_INFO_FIELD and group_wire_type == 2:
                    first_sprite_id = read_first_sprite_id(buffer, group_value)
                    break
    return object_id, first_sprite_id


def load_object_sprites(appearances_path):
    buffer = open(appearances_path, "rb").read()
    object_sprites = {}
    for field_number, wire_type, value in iterate_fields(buffer):
        if field_number != APPEARANCES_OBJECT_FIELD or wire_type != 2:
            continue
        object_id, first_sprite_id = read_object_first_sprite(buffer, value)
        if object_id and first_sprite_id:
            object_sprites[object_id] = first_sprite_id
    return object_sprites


def decode_sheet_rgba(sheet_path):
    raw = open(sheet_path, "rb").read()
    position = 0
    while raw[position] == 0:
        position += 1
    position += CIP_SIGNATURE_LENGTH
    while raw[position] & 0x80:
        position += 1
    position += 1
    properties = raw[position:position + 5]
    compressed_body = raw[position + 13:]
    bitmap = lzma.decompress(properties + b"\xff" * 8 + compressed_body, format=lzma.FORMAT_ALONE)
    pixel_offset = struct.unpack("<I", bitmap[10:14])[0]
    bgra_pixels = bitmap[pixel_offset:pixel_offset + SHEET_PIXEL_SIZE * SHEET_PIXEL_SIZE * 4]

    row_bytes = SHEET_PIXEL_SIZE * 4
    top_down_rows = [bgra_pixels[row * row_bytes:(row + 1) * row_bytes] for row in range(SHEET_PIXEL_SIZE - 1, -1, -1)]
    bgra_top_down = b"".join(top_down_rows)

    rgba = bytearray(len(bgra_top_down))
    rgba[0::4] = bgra_top_down[2::4]
    rgba[1::4] = bgra_top_down[1::4]
    rgba[2::4] = bgra_top_down[0::4]
    rgba[3::4] = bgra_top_down[3::4]
    return rgba


def extract_sprite_rgba(sheet_rgba, sprite_offset, sprite_width, sprite_height):
    columns = SHEET_PIXEL_SIZE // sprite_width
    sprite_row = sprite_offset // columns
    sprite_column = sprite_offset % columns
    sheet_row_bytes = SHEET_PIXEL_SIZE * 4
    sprite_row_bytes = sprite_width * 4
    rows = []
    for pixel_row in range(sprite_row * sprite_height, (sprite_row + 1) * sprite_height):
        row_start = pixel_row * sheet_row_bytes + sprite_column * sprite_row_bytes
        rows.append(bytes(sheet_rgba[row_start:row_start + sprite_row_bytes]))
    return rows


def encode_png(rows, width, height):
    def chunk(chunk_type, data):
        return struct.pack(">I", len(data)) + chunk_type + data + struct.pack(">I", zlib.crc32(chunk_type + data) & 0xFFFFFFFF)

    raw_image = b"".join(b"\x00" + row for row in rows)
    header = struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0)
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", header) + chunk(b"IDAT", zlib.compress(raw_image, 9)) + chunk(b"IEND", b"")


def find_appearances_file(things_directory, catalog):
    for entry in catalog:
        if entry.get("type") == "appearances":
            return os.path.join(things_directory, entry["file"])
    raise SystemExit("appearances entry not found in catalog-content.json")


def find_sheet_for_sprite(sprite_sheets, sprite_id):
    low = 0
    high = len(sprite_sheets) - 1
    while low <= high:
        middle = (low + high) // 2
        sheet = sprite_sheets[middle]
        if sprite_id < sheet["firstspriteid"]:
            high = middle - 1
        elif sprite_id > sheet["lastspriteid"]:
            low = middle + 1
        else:
            return sheet
    return None


XML_COMMENT_PATTERN = re.compile(r"<!--.*?-->", re.DOTALL)


def read_xml_without_comments(xml_path):
    with open(xml_path, encoding="latin-1") as xml_file:
        return XML_COMMENT_PATTERN.sub("", xml_file.read())


def load_item_names(items_xml_path):
    if not items_xml_path:
        return {}

    item_names = {}
    item_pattern = re.compile(r'<item\s+([^>]*?)/?>')
    attribute_pattern = re.compile(r'(\w+)="([^"]*)"')
    for match in item_pattern.finditer(read_xml_without_comments(items_xml_path)):
        attributes = dict(attribute_pattern.findall(match.group(1)))
        name = attributes.get("name")
        if not name:
            continue
        if "id" in attributes:
            item_names[int(attributes["id"])] = name
            continue
        if "fromid" in attributes and "toid" in attributes:
            for item_id in range(int(attributes["fromid"]), int(attributes["toid"]) + 1):
                item_names[item_id] = name
    return item_names


def write_gem_definitions(gems_xml_path, output_directory):
    gem_pattern = re.compile(r'<gem\s+([^>]*?)/?>')
    attribute_pattern = re.compile(r'(\w+)="([^"]*)"')
    gem_definitions = {}
    for match in gem_pattern.finditer(read_xml_without_comments(gems_xml_path)):
        attributes = dict(attribute_pattern.findall(match.group(1)))
        if "id" in attributes and "attribute" in attributes and "value" in attributes:
            gem_definitions[attributes["id"]] = {"attribute": attributes["attribute"], "value": float(attributes["value"])}

    with open(os.path.join(output_directory, "gems.json"), "w", encoding="utf-8") as gems_file:
        json.dump(gem_definitions, gems_file)
    print(f"gems.json: {len(gem_definitions)} gems")


def write_imbuement_names(imbuements_xml_path, output_directory):
    node_pattern = re.compile(r'<(base|imbuement)\s+([^>]*?)/?>')
    attribute_pattern = re.compile(r'(\w+)="([^"]*)"')
    base_names = {}
    imbuement_names = {}
    running_imbuement_id = 0
    for match in node_pattern.finditer(read_xml_without_comments(imbuements_xml_path)):
        attributes = dict(attribute_pattern.findall(match.group(2)))
        if match.group(1) == "base":
            base_names[attributes.get("id")] = attributes.get("name", "")
            continue

        running_imbuement_id += 1
        base_name = base_names.get(attributes.get("base"), "")
        imbuement_names[str(running_imbuement_id)] = f"{base_name} {attributes.get('name', '')}".strip()

    with open(os.path.join(output_directory, "imbuements.json"), "w", encoding="utf-8") as imbuements_file:
        json.dump(imbuement_names, imbuements_file)
    print(f"imbuements.json: {len(imbuement_names)} imbuements")


def generate(things_directory, output_directory, items_xml_path):
    catalog = json.load(open(os.path.join(things_directory, "catalog-content.json")))
    sprite_sheets = sorted((entry for entry in catalog if entry.get("type") == "sprite"), key=lambda entry: entry["firstspriteid"])
    object_sprites = load_object_sprites(find_appearances_file(things_directory, catalog))

    objects_by_sheet = {}
    for object_id, sprite_id in object_sprites.items():
        sheet = find_sheet_for_sprite(sprite_sheets, sprite_id)
        if sheet:
            objects_by_sheet.setdefault(sheet["file"], []).append((object_id, sprite_id, sheet))

    os.makedirs(output_directory, exist_ok=True)
    generated_count = 0
    for sheet_index, (sheet_file, sheet_objects) in enumerate(objects_by_sheet.items(), start=1):
        sheet_path = os.path.join(things_directory, sheet_file)
        if not os.path.isfile(sheet_path):
            continue

        sheet_rgba = decode_sheet_rgba(sheet_path)
        for object_id, sprite_id, sheet in sheet_objects:
            sprite_width, sprite_height = SPRITE_SIZE_BY_LAYOUT.get(sheet.get("spritetype", 0), (32, 32))
            rows = extract_sprite_rgba(sheet_rgba, sprite_id - sheet["firstspriteid"], sprite_width, sprite_height)
            with open(os.path.join(output_directory, f"{object_id}.png"), "wb") as image_file:
                image_file.write(encode_png(rows, sprite_width, sprite_height))
            generated_count += 1

        if sheet_index % 100 == 0:
            print(f"{sheet_index}/{len(objects_by_sheet)} sheets, {generated_count} images", flush=True)

    item_names = load_item_names(items_xml_path)
    if item_names:
        with open(os.path.join(output_directory, "names.json"), "w", encoding="utf-8") as names_file:
            json.dump({str(item_id): name for item_id, name in item_names.items() if item_id in object_sprites}, names_file, ensure_ascii=False)

    print(f"done: {generated_count} images in {output_directory}")


def main():
    parser = argparse.ArgumentParser(description="Generate item PNG images from the client appearances and sprite sheets.")
    parser.add_argument("things_directory", help="client data/things/<version> directory containing catalog-content.json")
    parser.add_argument("output_directory", help="directory where <itemId>.png files are written")
    parser.add_argument("--items-xml", help="server items.xml used to write names.json", default=None)
    parser.add_argument("--gems-xml", help="server gems.xml used to write gems.json", default=None)
    parser.add_argument("--imbuements-xml", help="server imbuements.xml used to write imbuements.json", default=None)
    parser.add_argument("--data-only", action="store_true", help="only write the json data files, skipping item images")
    arguments = parser.parse_args()

    os.makedirs(arguments.output_directory, exist_ok=True)
    if not arguments.data_only:
        generate(arguments.things_directory, arguments.output_directory, arguments.items_xml)
    if arguments.gems_xml:
        write_gem_definitions(arguments.gems_xml, arguments.output_directory)
    if arguments.imbuements_xml:
        write_imbuement_names(arguments.imbuements_xml, arguments.output_directory)


if __name__ == "__main__":
    sys.exit(main())
