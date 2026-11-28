<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

$all_news_posts = home_load_latest_news();
$all_site_updates = home_load_site_updates();
?>
<section class="home">
	<div class="register-hero">
		<span class="register-eyebrow"><i class="fa fa-bullhorn"></i> Novidades</span>
		<h1>Novidades do Noxum OT</h1>
		<p>Notícias da equipe e tudo o que mudou no servidor desde a abertura.</p>
	</div>

	<?php if (!empty($all_news_posts)): ?>
		<div class="home-section">
			<div class="home-section-header">
				<h2><i class="fa fa-newspaper-o"></i> Notícias</h2>
			</div>
			<?php foreach ($all_news_posts as $news_post): ?>
				<article class="home-update home-update-news">
					<header>
						<span class="home-update-tag">Notícia</span>
						<time><?php echo getClock($news_post['date'], true); ?></time>
					</header>
					<h3><a href="index.php?view=<?php echo (int) $news_post['id']; ?>"><?php echo htmlspecialchars(home_strip_news_markup($news_post['title'])); ?></a></h3>
					<p><?php echo htmlspecialchars(home_preview_news_text($news_post['text'])); ?></p>
					<a class="home-update-more" href="index.php?view=<?php echo (int) $news_post['id']; ?>">Ler mais <i class="fa fa-angle-right"></i></a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="home-section">
		<div class="home-section-header">
			<h2><i class="fa fa-wrench"></i> Atualizações</h2>
		</div>
		<?php foreach ($all_site_updates as $update_index => $site_update): ?>
			<article class="home-update<?php if ($update_index === 0) echo ' is-latest'; ?>">
				<header>
					<span class="home-update-tag"><?php echo htmlspecialchars($site_update['tag']); ?></span>
					<time><?php echo home_format_update_date($site_update['date']); ?></time>
				</header>
				<h3><?php echo htmlspecialchars($site_update['title']); ?></h3>
				<ul>
					<?php foreach ($site_update['items'] as $update_item): ?>
						<li><?php echo htmlspecialchars($update_item); ?></li>
					<?php endforeach; ?>
				</ul>
			</article>
		<?php endforeach; ?>
	</div>
</section>
<?php include 'layout/overall/footer.php'; ?>
