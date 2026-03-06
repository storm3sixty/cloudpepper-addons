(function ($) {
	'use strict';

	function nextIndex(selector) {
		var max = -1;
		$(selector).each(function () {
			var current = parseInt($(this).attr('data-index') || $(this).attr('data-attribute-index') || $(this).attr('data-option-index'), 10);
			if (!isNaN(current) && current > max) {
				max = current;
			}
		});
		return max + 1;
	}

	function nextOptionIndex($attribute) {
		return nextIndex($attribute.find('.tpcw-option-row'));
	}

	function optionTemplate(attributeIndex, optionIndex) {
		var base = 'tpcw_config[attributes][' + attributeIndex + '][options][' + optionIndex + ']';
		return '<div class="tpcw-option-row" data-option-index="' + optionIndex + '">' +
			'<div class="tpcw-grid tpcw-option-grid">' +
			'<p><label>Option value key</label><input type="text" name="' + base + '[value_key]" value="" /></p>' +
			'<p><label>Option label</label><input type="text" name="' + base + '[label]" value="" /></p>' +
			'<p><label>Option description</label><input type="text" name="' + base + '[description]" value="" /></p>' +
			'<p><label>Badge text</label><input type="text" name="' + base + '[badge]" value="" /></p>' +
			'<p><label>Icon / image attachment ID</label><input type="number" min="0" class="small-text tpcw-media-id" name="' + base + '[image_id]" value="0" /> <button type="button" class="button tpcw-media-select">Select media</button></p>' +
			'<p><label><input type="checkbox" name="' + base + '[show_option]" value="1" checked="checked" /> Show option</label><br /><label><input type="radio" name="tpcw_config[attributes][' + attributeIndex + '][default_option]" value="" class="tpcw-default-option" /> Default option</label></p>' +
			'</div><p><button type="button" class="button-link-delete tpcw-remove-option">Remove option</button></p></div>';
	}

	function attributeTemplate(attributeIndex) {
		var base = 'tpcw_config[attributes][' + attributeIndex + ']';
		return '<div class="tpcw-attribute" data-attribute-index="' + attributeIndex + '">' +
			'<div class="tpcw-attribute-header"><button type="button" class="button-link tpcw-toggle-attribute">▾</button><strong>New attribute</strong><button type="button" class="button-link-delete tpcw-remove-attribute">Remove attribute</button></div>' +
			'<div class="tpcw-attribute-body"><div class="tpcw-grid">' +
			'<p><label>Attribute key</label><input type="text" name="' + base + '[attribute_key]" value="" /></p>' +
			'<p><label>Attribute label</label><input type="text" class="tpcw-attribute-label" name="' + base + '[attribute_label]" value="" /></p>' +
			'<p><label>Frontend display type</label><select name="' + base + '[display_type]"><option value="dropdown">Dropdown</option><option value="icon">Icon</option><option value="image">Image</option><option value="text">Text</option></select></p>' +
			'<p><label>Help text / tooltip</label><input type="text" name="' + base + '[help_text]" value="" /></p>' +
			'<p><label>Sort order</label><input type="number" min="0" name="' + base + '[sort_order]" value="0" /></p>' +
			'<p><label><input type="checkbox" name="' + base + '[show_attribute]" value="1" checked="checked" /> Show attribute</label><br /><label><input type="checkbox" name="' + base + '[required]" value="1" /> Required</label></p>' +
			'</div><div class="tpcw-option-builder" data-attribute-index="' + attributeIndex + '"></div>' +
			'<p><button type="button" class="button tpcw-add-option">Add option</button></p></div></div>';
	}

	function matrixServiceTemplate(index) {
		var base = 'tpcw_config[matrix][services][' + index + ']';
		return '<div class="tpcw-repeater-row tpcw-matrix-service-row" data-index="' + index + '"><div class="tpcw-grid">' +
			'<p><label>Service key</label><input type="text" class="tpcw-service-key" name="' + base + '[service_key]" value="" /></p>' +
			'<p><label>Service label</label><input type="text" name="' + base + '[service_label]" value="" /></p>' +
			'<p><label>Service description</label><input type="text" name="' + base + '[service_description]" value="" /></p>' +
			'<p><label>Estimated production days</label><input type="number" min="0" name="' + base + '[production_days]" value="0" /></p>' +
			'<p><label>Estimated delivery label</label><input type="text" name="' + base + '[delivery_label]" value="" /></p>' +
			'<p><label>Badge text</label><input type="text" name="' + base + '[badge]" value="" /></p>' +
			'<p><label><input type="checkbox" name="' + base + '[show_service]" value="1" checked="checked" /> Show service</label></p>' +
			'<p><label>Sort order</label><input type="number" min="0" name="' + base + '[sort_order]" value="0" /></p>' +
			'</div><p><button type="button" class="button-link-delete tpcw-remove-row">Remove service</button></p></div>';
	}

	function matrixQuantityTemplate(index) {
		var base = 'tpcw_config[matrix][quantities][' + index + ']';
		return '<div class="tpcw-repeater-row tpcw-matrix-quantity-row" data-index="' + index + '"><div class="tpcw-grid">' +
			'<p><label>Quantity value</label><input type="number" min="1" class="tpcw-quantity-value" name="' + base + '[quantity]" value="1" /></p>' +
			'<p><label><input type="checkbox" name="' + base + '[show_quantity]" value="1" checked="checked" /> Show quantity</label></p>' +
			'<p><label>Sort order</label><input type="number" min="0" name="' + base + '[sort_order]" value="0" /></p>' +
			'</div><p><button type="button" class="button-link-delete tpcw-remove-row">Remove quantity</button></p></div>';
	}

	function bindDefaultValueMirror($scope) {
		$scope.find('.tpcw-option-row input[name*="[value_key]"]').on('input', function () {
			var $row = $(this).closest('.tpcw-option-row');
			$row.find('.tpcw-default-option').val($(this).val());
		});
	}

	function bindMediaPicker() {
		$(document).on('click', '.tpcw-media-select', function (event) {
			event.preventDefault();
			var $button = $(this);
			var $input = $button.siblings('.tpcw-media-id');
			var frame = wp.media({
				title: 'Select media',
				button: { text: 'Use this media' },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var selection = frame.state().get('selection').first().toJSON();
				$input.val(selection.id);
			});
			frame.open();
		});
	}

	function collectServices($servicesWrap) {
		var services = [];
		$servicesWrap.find('.tpcw-matrix-service-row').each(function () {
			var $row = $(this);
			var key = String($row.find('.tpcw-service-key').val() || '').trim();
			if (key) {
				services.push({ key: key, row: $row });
			}
		});
		return services;
	}

	function collectQuantities($quantitiesWrap) {
		var quantities = [];
		$quantitiesWrap.find('.tpcw-matrix-quantity-row').each(function () {
			var $row = $(this);
			var qty = parseInt($row.find('.tpcw-quantity-value').val(), 10);
			if (!isNaN(qty) && qty > 0) {
				quantities.push({ value: qty, row: $row });
			}
		});
		return quantities;
	}

	function markUniquenessIssues($rows, fieldSelector, isNumeric) {
		var seen = {};
		$rows.each(function () {
			var $row = $(this);
			var raw = $row.find(fieldSelector).val();
			var key = isNumeric ? String(parseInt(raw, 10)) : String(raw || '').trim().toLowerCase();
			var invalid = (!key || key === 'nan');
			if (!invalid && seen[key]) {
				invalid = true;
				seen[key].addClass('tpcw-duplicate');
			}
			if (!invalid) {
				seen[key] = $row;
			}
			$row.toggleClass('tpcw-duplicate', invalid);
		});
	}

	function mapCellsFromExisting($grid) {
		var map = {};
		$grid.find('td[data-qty][data-service]').each(function () {
			var $cell = $(this);
			var key = $cell.data('qty') + '|' + $cell.data('service');
			map[key] = {
				base_price: $cell.find('.tpcw-cell-base').val() || '0',
				final_price: $cell.find('.tpcw-cell-final').val() || '0',
				unit_price: $cell.find('.tpcw-cell-unit').val() || '0',
				available: $cell.find('.tpcw-cell-available').is(':checked'),
				recommended: $cell.find('.tpcw-cell-recommended').is(':checked')
			};
		});
		return map;
	}

	function buildGrid($grid, services, quantities, previousMap) {
		var html = '<thead><tr><th>Quantity</th>';
		services.forEach(function (service) {
			html += '<th>' + service.key + '</th>';
		});
		html += '</tr></thead><tbody>';

		quantities.forEach(function (qty) {
			html += '<tr><th scope="row">' + qty.value + '</th>';
			services.forEach(function (service) {
				var pairKey = qty.value + '|' + service.key;
				var cell = previousMap[pairKey] || { base_price: '0', final_price: '0', unit_price: '0', available: true, recommended: false };
				html += '<td data-qty="' + qty.value + '" data-service="' + service.key + '">' +
					'<label>Base <input type="number" step="0.01" min="0" class="tpcw-cell-base" name="tpcw_config[matrix][grid][' + qty.value + '][' + service.key + '][base_price]" value="' + cell.base_price + '" /></label>' +
					'<label>Display <input type="number" step="0.01" min="0" class="tpcw-cell-final" name="tpcw_config[matrix][grid][' + qty.value + '][' + service.key + '][final_price]" value="' + cell.final_price + '" /></label>' +
					'<label>Unit <input type="number" step="0.0001" min="0" class="tpcw-cell-unit" name="tpcw_config[matrix][grid][' + qty.value + '][' + service.key + '][unit_price]" value="' + cell.unit_price + '" /></label>' +
					'<label><input type="checkbox" class="tpcw-cell-available" name="tpcw_config[matrix][grid][' + qty.value + '][' + service.key + '][available]" value="1"' + (cell.available ? ' checked="checked"' : '') + ' /> Available</label>' +
					'<label><input type="checkbox" class="tpcw-cell-recommended" name="tpcw_config[matrix][grid][' + qty.value + '][' + service.key + '][recommended]" value="1"' + (cell.recommended ? ' checked="checked"' : '') + ' /> Recommended</label>' +
					'</td>';
			});
			html += '</tr>';
		});
		html += '</tbody>';
		$grid.html(html);
		highlightMatrixCells($grid);
	}

	function highlightMatrixCells($grid) {
		$grid.find('td').removeClass('tpcw-cell-recommended-mark tpcw-cell-best-value');
		var bestUnit = null;
		$grid.find('td').each(function () {
			var $td = $(this);
			var unit = parseFloat($td.find('.tpcw-cell-unit').val());
			var available = $td.find('.tpcw-cell-available').is(':checked');
			if (available && !isNaN(unit) && unit > 0 && (bestUnit === null || unit < bestUnit)) {
				bestUnit = unit;
			}
			if ($td.find('.tpcw-cell-recommended').is(':checked')) {
				$td.addClass('tpcw-cell-recommended-mark');
			}
		});
		if (bestUnit !== null) {
			$grid.find('td').each(function () {
				var $td = $(this);
				var unit = parseFloat($td.find('.tpcw-cell-unit').val());
				var available = $td.find('.tpcw-cell-available').is(':checked');
				if (available && !isNaN(unit) && unit === bestUnit) {
					$td.addClass('tpcw-cell-best-value');
				}
			});
		}
	}

	$(function () {
		var $builder = $('#tpcw-attribute-builder');
		if ($builder.length) {
			bindDefaultValueMirror($builder);
			$('#tpcw-add-attribute').on('click', function () {
				var index = nextIndex('#tpcw-attribute-builder .tpcw-attribute');
				$builder.append($(attributeTemplate(index)));
			});
			$builder.on('click', '.tpcw-add-option', function () {
				var $attribute = $(this).closest('.tpcw-attribute');
				var attributeIndex = $attribute.attr('data-attribute-index');
				var optionIndex = nextOptionIndex($attribute);
				var $newOption = $(optionTemplate(attributeIndex, optionIndex));
				$attribute.find('.tpcw-option-builder').append($newOption);
				bindDefaultValueMirror($newOption);
			});
			$builder.on('click', '.tpcw-remove-attribute', function () { $(this).closest('.tpcw-attribute').remove(); });
			$builder.on('click', '.tpcw-remove-option', function () { $(this).closest('.tpcw-option-row').remove(); });
			$builder.on('click', '.tpcw-toggle-attribute', function () { $(this).closest('.tpcw-attribute').toggleClass('is-collapsed'); });
			$builder.on('input', '.tpcw-attribute-label', function () {
				var label = $(this).val() || 'New attribute';
				$(this).closest('.tpcw-attribute').find('.tpcw-attribute-header strong').text(label);
			});
		}

		var $services = $('#tpcw-matrix-services');
		var $quantities = $('#tpcw-matrix-quantities');
		var $grid = $('#tpcw-matrix-grid');

		function rebuildGrid() {
			if (!$grid.length) {
				return;
			}
			markUniquenessIssues($services.find('.tpcw-matrix-service-row'), '.tpcw-service-key', false);
			markUniquenessIssues($quantities.find('.tpcw-matrix-quantity-row'), '.tpcw-quantity-value', true);
			var services = collectServices($services);
			var quantities = collectQuantities($quantities);
			var previous = mapCellsFromExisting($grid);
			buildGrid($grid, services, quantities, previous);
		}

		$('#tpcw-add-matrix-service').on('click', function () {
			var index = nextIndex('#tpcw-matrix-services .tpcw-repeater-row');
			$services.append($(matrixServiceTemplate(index)));
			rebuildGrid();
		});
		$('#tpcw-add-matrix-quantity').on('click', function () {
			var index = nextIndex('#tpcw-matrix-quantities .tpcw-repeater-row');
			$quantities.append($(matrixQuantityTemplate(index)));
			rebuildGrid();
		});

		$(document).on('click', '.tpcw-remove-row', function () {
			$(this).closest('.tpcw-repeater-row').remove();
			rebuildGrid();
		});

		$(document).on('input change', '.tpcw-matrix-service-row input, .tpcw-matrix-quantity-row input', function () {
			rebuildGrid();
		});
		$(document).on('input change', '#tpcw-matrix-grid input', function () {
			highlightMatrixCells($grid);
		});

		rebuildGrid();
		bindMediaPicker();
	});
})(jQuery);
