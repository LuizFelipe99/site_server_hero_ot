(function () {
	var MAXIMUM_GEMS_PER_TYPE = 2;
	var DEFAULT_MECHANICS_TAB = 'forja';
	var EXAMPLE_SPELL_DAMAGE = 1000;

	function forEachElement(elementList, callback) {
		Array.prototype.forEach.call(elementList, callback);
	}

	function formatNumber(numberValue) {
		return numberValue.toLocaleString('pt-BR');
	}

	function setupMainTabs() {
		var tabButtons = document.querySelectorAll('[data-mechanics-tab]');

		if (tabButtons.length === 0) {
			return;
		}

		function showTab(tabName, shouldUpdateHash) {
			var targetPanel = document.getElementById(tabName);

			if (!targetPanel) {
				tabName = DEFAULT_MECHANICS_TAB;
			}

			forEachElement(tabButtons, function (tabButton) {
				var isSelected = tabButton.getAttribute('data-mechanics-tab') === tabName;
				tabButton.classList.toggle('is-active', isSelected);
				tabButton.setAttribute('aria-selected', isSelected ? 'true' : 'false');
				document.getElementById(tabButton.getAttribute('data-mechanics-tab')).hidden = !isSelected;
			});

			if (shouldUpdateHash && window.history.replaceState) {
				window.history.replaceState(null, '', '#' + tabName);
			}
		}

		forEachElement(tabButtons, function (tabButton) {
			tabButton.addEventListener('click', function () {
				showTab(tabButton.getAttribute('data-mechanics-tab'), true);
			});
		});

		window.addEventListener('hashchange', function () {
			showTab(window.location.hash.replace('#', '') || DEFAULT_MECHANICS_TAB, false);
		});

		showTab(window.location.hash.replace('#', '') || DEFAULT_MECHANICS_TAB, false);
	}

	function setupForgeSubtabs() {
		var subtabButtons = document.querySelectorAll('[data-mechanics-subtab]');

		forEachElement(subtabButtons, function (subtabButton) {
			subtabButton.addEventListener('click', function () {
				var selectedSubtab = subtabButton.getAttribute('data-mechanics-subtab');

				forEachElement(subtabButtons, function (otherButton) {
					otherButton.classList.toggle('is-active', otherButton === subtabButton);
				});

				forEachElement(document.querySelectorAll('[data-mechanics-subpanel]'), function (subpanel) {
					subpanel.hidden = subpanel.getAttribute('data-mechanics-subpanel') !== selectedSubtab;
				});
			});
		});
	}

	function setupRecipeFilters() {
		var vocationButtons = document.querySelectorAll('[data-vocation-filter]');
		var searchInput = document.querySelector('[data-recipe-search]');
		var emptyMessage = document.querySelector('[data-recipe-empty]');
		var selectedVocation = 'all';

		if (!searchInput) {
			return;
		}

		function applyFilters() {
			var searchTerm = searchInput.value.trim().toLowerCase();
			var visibleRecipes = 0;

			forEachElement(document.querySelectorAll('.mechanics-recipe'), function (recipeCard) {
				var matchesVocation = selectedVocation === 'all' || recipeCard.getAttribute('data-vocation') === selectedVocation;
				var matchesSearch = searchTerm === '' || recipeCard.getAttribute('data-name').indexOf(searchTerm) !== -1;
				var isVisible = matchesVocation && matchesSearch;

				recipeCard.hidden = !isVisible;
				visibleRecipes += isVisible ? 1 : 0;
			});

			emptyMessage.hidden = visibleRecipes > 0;
		}

		forEachElement(vocationButtons, function (vocationButton) {
			vocationButton.addEventListener('click', function () {
				selectedVocation = vocationButton.getAttribute('data-vocation-filter');

				forEachElement(vocationButtons, function (otherButton) {
					otherButton.classList.toggle('is-active', otherButton === vocationButton);
				});

				applyFilters();
			});
		});

		searchInput.addEventListener('input', applyFilters);
	}

	function setupFusionSimulator() {
		var fusionSimulator = document.querySelector('[data-fusion-simulator]');

		if (!fusionSimulator) {
			return;
		}

		var fusionButtons = fusionSimulator.querySelectorAll('[data-fusion-index]');
		var resultLabel = fusionSimulator.querySelector('[data-fusion-result]');
		var selectedFusionIndex = '0';

		forEachElement(fusionButtons, function (fusionButton) {
			fusionButton.addEventListener('click', function () {
				selectedFusionIndex = fusionButton.getAttribute('data-fusion-index');
				resultLabel.textContent = '';
				resultLabel.className = 'mechanics-fusion-result';

				forEachElement(fusionButtons, function (otherButton) {
					otherButton.classList.toggle('is-active', otherButton === fusionButton);
				});

				forEachElement(fusionSimulator.querySelectorAll('[data-fusion-stage]'), function (fusionStage) {
					fusionStage.hidden = fusionStage.getAttribute('data-fusion-stage') !== selectedFusionIndex;
				});
			});
		});

		fusionSimulator.querySelector('[data-fusion-try]').addEventListener('click', function () {
			var activeStage = fusionSimulator.querySelector('[data-fusion-stage="' + selectedFusionIndex + '"]');
			var successChance = parseInt(activeStage.getAttribute('data-fusion-chance'), 10);
			var hasSucceeded = Math.random() * 100 < successChance;

			activeStage.classList.remove('is-success', 'is-failure');
			void activeStage.offsetWidth;
			activeStage.classList.add(hasSucceeded ? 'is-success' : 'is-failure');
			resultLabel.className = 'mechanics-fusion-result ' + (hasSucceeded ? 'is-success' : 'is-failure');
			resultLabel.textContent = hasSucceeded ? 'Sucesso! Você ganhou o crafter do próximo nível.' : 'Falhou! Os 4 crafters foram perdidos.';
		});
	}

	function setupGemBuilder() {
		var gemBuilder = document.querySelector('[data-gem-builder]');
		var gemValuesByType = window.heroMechanicsGems || {};
		var gemTypes = window.heroMechanicsGemTypes || {};
		var gemSpriteStyles = window.heroMechanicsGemSprites || {};

		if (!gemBuilder) {
			return;
		}

		var gemSlots = gemBuilder.querySelectorAll('[data-gem-slot]');
		var gemMessage = gemBuilder.querySelector('[data-gem-message]');
		var gemSummary = gemBuilder.querySelector('[data-gem-summary]');
		var socketedGems = [];

		function countSocketedType(gemType) {
			return socketedGems.filter(function (socketedGem) {
				return socketedGem && socketedGem.type === gemType;
			}).length;
		}

		function showGemMessage(messageText, isWarning) {
			gemMessage.textContent = messageText;
			gemMessage.classList.toggle('is-warning', isWarning);
		}

		function renderGemSummary() {
			var totalsByType = {};

			socketedGems.forEach(function (socketedGem) {
				if (!socketedGem) {
					return;
				}

				totalsByType[socketedGem.type] = (totalsByType[socketedGem.type] || 0) + socketedGem.value;
			});

			var summaryRows = Object.keys(gemTypes).filter(function (gemType) {
				return totalsByType[gemType];
			}).map(function (gemType) {
				return '<div style="--gem-color: ' + gemTypes[gemType].color + ';"><dt>' + gemTypes[gemType].label + '</dt><dd>+' + formatNumber(totalsByType[gemType]) + gemTypes[gemType].unit + '</dd></div>';
			});

			gemSummary.innerHTML = summaryRows.length > 0 ? summaryRows.join('') : '<div class="is-empty"><dt>Bônus total</dt><dd>nenhum</dd></div>';
		}

		function renderGemSlots() {
			forEachElement(gemSlots, function (gemSlot, slotIndex) {
				var socketedGem = socketedGems[slotIndex];

				if (!socketedGem) {
					gemSlot.className = 'mechanics-gem-slot';
					gemSlot.removeAttribute('style');
					gemSlot.innerHTML = '';
					gemSlot.setAttribute('aria-label', 'Espaço vazio');
					return;
				}

				gemSlot.className = 'mechanics-gem-slot is-filled';
				gemSlot.style.setProperty('--gem-color', gemTypes[socketedGem.type].color);
				gemSlot.innerHTML = '<span class="mechanics-item" style="' + gemSpriteStyles[socketedGem.type][socketedGem.level - 1] + '"></span><small>Nv. ' + socketedGem.level + '</small>';
				gemSlot.setAttribute('aria-label', gemTypes[socketedGem.type].label + ' nível ' + socketedGem.level + ', clique para tirar');
			});

			renderGemSummary();
		}

		function socketGem(gemType, gemLevel) {
			if (countSocketedType(gemType) >= MAXIMUM_GEMS_PER_TYPE) {
				showGemMessage('Você já tem 2 gemas de ' + gemTypes[gemType].label.toLowerCase() + ' encaixadas. Esse é o limite por tipo.', true);
				return;
			}

			var freeSlotIndex = -1;

			for (var slotIndex = 0; slotIndex < gemSlots.length; slotIndex++) {
				if (!socketedGems[slotIndex]) {
					freeSlotIndex = slotIndex;
					break;
				}
			}

			if (freeSlotIndex === -1) {
				showGemMessage('Todos os ' + gemSlots.length + ' espaços estão ocupados. Clique numa gema para tirar.', true);
				return;
			}

			socketedGems[freeSlotIndex] = { type: gemType, level: gemLevel, value: gemValuesByType[gemType][gemLevel - 1] };
			showGemMessage('Gema de ' + gemTypes[gemType].label.toLowerCase() + ' nível ' + gemLevel + ' encaixada.', false);
			renderGemSlots();
		}

		forEachElement(gemBuilder.querySelectorAll('[data-gem-type]'), function (gemOption) {
			var gemType = gemOption.getAttribute('data-gem-type');
			var levelInput = gemOption.querySelector('[data-gem-level]');

			levelInput.addEventListener('input', function () {
				var gemLevel = parseInt(levelInput.value, 10);
				gemOption.querySelector('[data-gem-level-label]').textContent = gemLevel;
				gemOption.querySelector('[data-gem-value-label]').textContent = formatNumber(gemValuesByType[gemType][gemLevel - 1]);
				gemOption.querySelector('[data-gem-sprite]').setAttribute('style', gemSpriteStyles[gemType][gemLevel - 1]);
			});

			gemOption.querySelector('[data-gem-add]').addEventListener('click', function () {
				socketGem(gemType, parseInt(levelInput.value, 10));
			});
		});

		forEachElement(gemSlots, function (gemSlot, slotIndex) {
			function removeSocketedGem(clickEvent) {
				if (!socketedGems[slotIndex]) {
					return;
				}

				clickEvent.preventDefault();
				socketedGems[slotIndex] = null;
				showGemMessage('Gema removida. No jogo ela volta para a sua mochila.', false);
				renderGemSlots();
			}

			gemSlot.addEventListener('click', removeSocketedGem);
			gemSlot.addEventListener('contextmenu', removeSocketedGem);
		});

		renderGemSlots();
	}

	function setupElementalSimulator() {
		var elementalSimulator = document.querySelector('[data-elemental-simulator]');

		if (!elementalSimulator) {
			return;
		}

		var totalPoints = parseInt(elementalSimulator.getAttribute('data-elemental-points'), 10);
		var freePointsLabel = elementalSimulator.querySelector('[data-elemental-free]');
		var exampleLabel = elementalSimulator.querySelector('[data-elemental-example]');
		var elementRows = elementalSimulator.querySelectorAll('[data-element]');
		var pointsByElement = {};

		function countSpentPoints() {
			return Object.keys(pointsByElement).reduce(function (spentPoints, elementKey) {
				return spentPoints + pointsByElement[elementKey];
			}, 0);
		}

		function describeStrongestElement() {
			var strongestElementRow = null;
			var strongestPoints = 0;

			forEachElement(elementRows, function (elementRow) {
				var elementPoints = pointsByElement[elementRow.getAttribute('data-element')] || 0;

				if (elementPoints > strongestPoints) {
					strongestPoints = elementPoints;
					strongestElementRow = elementRow;
				}
			});

			if (!strongestElementRow) {
				return 'Coloque pontos em um elemento para ver o efeito no dano.';
			}

			var elementName = strongestElementRow.querySelector('strong').textContent.toLowerCase();
			var boostedDamage = Math.floor(EXAMPLE_SPELL_DAMAGE * (1 + strongestPoints / 100));
			return 'Exemplo: um golpe de ' + elementName + ' que daria ' + formatNumber(EXAMPLE_SPELL_DAMAGE) + ' de dano passa a dar ' + formatNumber(boostedDamage) + ' (+' + strongestPoints + '%).';
		}

		function renderElementalBuild() {
			var freePoints = totalPoints - countSpentPoints();
			freePointsLabel.textContent = freePoints;

			forEachElement(elementRows, function (elementRow) {
				var elementPoints = pointsByElement[elementRow.getAttribute('data-element')] || 0;
				elementRow.querySelector('[data-element-value]').textContent = '+' + elementPoints + '%';
				elementRow.querySelector('[data-element-bar]').style.width = (totalPoints > 0 ? elementPoints / totalPoints * 100 : 0) + '%';
				elementRow.querySelector('[data-element-step="1"]').disabled = freePoints === 0;
				elementRow.querySelector('[data-element-step="-1"]').disabled = elementPoints === 0;
				elementRow.classList.toggle('is-active', elementPoints > 0);
			});

			exampleLabel.textContent = describeStrongestElement();
		}

		forEachElement(elementRows, function (elementRow) {
			var elementKey = elementRow.getAttribute('data-element');

			forEachElement(elementRow.querySelectorAll('[data-element-step]'), function (stepButton) {
				stepButton.addEventListener('click', function () {
					var pointStep = parseInt(stepButton.getAttribute('data-element-step'), 10);
					var currentPoints = pointsByElement[elementKey] || 0;

					if (pointStep > 0 && countSpentPoints() >= totalPoints) {
						return;
					}

					pointsByElement[elementKey] = Math.max(0, currentPoints + pointStep);
					renderElementalBuild();
				});
			});
		});

		elementalSimulator.querySelector('[data-elemental-reset]').addEventListener('click', function () {
			pointsByElement = {};
			renderElementalBuild();
		});

		renderElementalBuild();
	}

	function setupBattlePassDemo() {
		var passDemo = document.querySelector('[data-pass-demo]');

		if (!passDemo) {
			return;
		}

		var dayButtons = passDemo.querySelectorAll('[data-pass-day]');
		var proToggle = passDemo.querySelector('[data-pass-pro]');

		forEachElement(dayButtons, function (dayButton) {
			dayButton.addEventListener('click', function () {
				var selectedDay = dayButton.getAttribute('data-pass-day');

				forEachElement(dayButtons, function (otherButton) {
					otherButton.classList.toggle('is-active', otherButton === dayButton);
				});

				forEachElement(passDemo.querySelectorAll('[data-pass-detail]'), function (dayDetail) {
					dayDetail.hidden = dayDetail.getAttribute('data-pass-detail') !== selectedDay;
				});
			});
		});

		proToggle.addEventListener('change', function () {
			passDemo.classList.toggle('has-pro', proToggle.checked);
		});
	}

	function setupOutfitGallery() {
		var outfitGallery = document.querySelector('[data-outfit-gallery]');

		if (!outfitGallery) {
			return;
		}

		var outfitCards = outfitGallery.querySelectorAll('.mechanics-outfit');
		var sexButtons = outfitGallery.querySelectorAll('[data-outfit-sex]');
		var searchInput = outfitGallery.querySelector('[data-outfit-search]');
		var emptyMessage = outfitGallery.querySelector('[data-outfit-empty]');
		var resultLabel = outfitGallery.querySelector('[data-outfit-result]');

		function applyOutfitSearch() {
			var searchTerm = searchInput.value.trim().toLowerCase();
			var visibleOutfits = 0;

			forEachElement(outfitCards, function (outfitCard) {
				var isVisible = searchTerm === '' || outfitCard.getAttribute('data-outfit-name').indexOf(searchTerm) !== -1;
				outfitCard.hidden = !isVisible;
				visibleOutfits += isVisible ? 1 : 0;
			});

			emptyMessage.hidden = visibleOutfits > 0;
		}

		forEachElement(sexButtons, function (sexButton) {
			sexButton.addEventListener('click', function () {
				var selectedSex = sexButton.getAttribute('data-outfit-sex');

				forEachElement(sexButtons, function (otherButton) {
					otherButton.classList.toggle('is-active', otherButton === sexButton);
				});

				forEachElement(outfitGallery.querySelectorAll('.mechanics-outfit-sprite'), function (outfitSprite) {
					outfitSprite.setAttribute('style', outfitSprite.getAttribute('data-' + selectedSex + '-style'));
				});
			});
		});

		searchInput.addEventListener('input', applyOutfitSearch);

		outfitGallery.querySelector('[data-outfit-roll]').addEventListener('click', function () {
			var chosenCard = outfitCards[Math.floor(Math.random() * outfitCards.length)];

			searchInput.value = '';
			applyOutfitSearch();

			forEachElement(outfitCards, function (outfitCard) {
				outfitCard.classList.toggle('is-chosen', outfitCard === chosenCard);
			});

			resultLabel.textContent = 'Você ganhou a outfit ' + chosenCard.querySelector('.mechanics-outfit-name').textContent + ' completa!';
			chosenCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
	}

	setupMainTabs();
	setupForgeSubtabs();
	setupRecipeFilters();
	setupFusionSimulator();
	setupGemBuilder();
	setupElementalSimulator();
	setupBattlePassDemo();
	setupOutfitGallery();
})();
