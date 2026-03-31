(function () {
	function byId(key) {
		return document.getElementById('mrp-setting-' + key);
	}

	function getValue(key, fallback) {
		var field = byId(key);
		if (!field) {
			return fallback;
		}

		if (field.type === 'checkbox') {
			return field.checked ? '1' : '0';
		}

		if (field.value === '') {
			return fallback;
		}

		return field.value;
	}

	function setStyleVar(preview, name, value) {
		preview.style.setProperty(name, value);
	}

	function toggleVisibility(element, show) {
		if (!element) {
			return;
		}
		element.hidden = !show;
	}

	function buildShadow(shadow) {
		if (shadow === 'none') {
			return 'none';
		}
		if (shadow === 'medium') {
			return '0 16px 36px rgba(17, 24, 39, 0.12)';
		}
		if (shadow === 'strong') {
			return '0 20px 44px rgba(17, 24, 39, 0.16)';
		}
		return '0 12px 28px rgba(17, 24, 39, 0.08)';
	}

	function buildRatio(ratio) {
		if (ratio === 'square') {
			return '1 / 1';
		}
		if (ratio === 'portrait') {
			return '4 / 5';
		}
		if (ratio === 'auto') {
			return 'auto';
		}
		return '16 / 9';
	}

	function buildFlexAlign(value) {
		if (value === 'center') {
			return 'center';
		}
		if (value === 'right') {
			return 'flex-end';
		}
		return 'flex-start';
	}

	function buildHeadingTag(tag, text) {
		var allowed = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'];
		var safeTag = allowed.indexOf(tag) !== -1 ? tag : 'h2';
		var current = document.querySelector('[data-mrp-preview="sectionTitle"]');
		if (!current || current.tagName.toLowerCase() === safeTag) {
			if (current) {
				current.textContent = text;
			}
			return;
		}

		var replacement = document.createElement(safeTag);
		replacement.className = current.className;
		replacement.setAttribute('data-mrp-preview', 'sectionTitle');
		replacement.textContent = text;
		current.parentNode.replaceChild(replacement, current);
	}

	function updatePreviewCards(count) {
		document.querySelectorAll('[data-mrp-preview-card]').forEach(function (card, index) {
			card.hidden = index >= count;
		});
	}

	function updatePreviewFrameWidth(width) {
		var canvas = document.querySelector('.mrp-admin-preview-canvas');
		if (!canvas) {
			return;
		}
		canvas.style.setProperty('--mrp-preview-frame-width', width + 'px');
	}

	function updatePreview() {
		var preview = document.querySelector('.mrp-admin-preview-block');
		if (!preview) {
			return;
		}

		var sectionTitle = getValue('sectionTitle', 'Related Posts');
		var sectionSubtitle = getValue('sectionSubtitle', 'Optional subtitle text can reinforce the section context.');
		var buttonText = getValue('buttonText', 'Read more');
		var excerptText = 'This preview card updates as you change typography, spacing, colors, image behavior and button settings in the global defaults.';
		var titleText = 'Example related post';
		var showExcerpt = getValue('showExcerpt', '1') === '1';
		var showDate = getValue('showDate', '0') === '1';
		var showCategory = getValue('showCategory', '0') === '1';
		var showButton = getValue('showButton', '1') === '1';
		var showImage = getValue('showImage', '1') === '1';
		var fullCardLink = getValue('fullCardLink', '1') === '1';
		var columnsMobile = getValue('columnsMobile', '1');
		var columnsTablet = getValue('columnsTablet', '2');
		var columnsDesktop = getValue('columnsDesktop', '3');
		var gap = getValue('gap', '24');
		var imageHeight = getValue('imageHeight', '0');
		var sectionTitleTag = getValue('sectionTitleTag', 'h2');
		var previewWidthControl = document.getElementById('mrp-preview-width-control');
		var previewCardsControl = document.getElementById('mrp-preview-cards-control');
		var dateNodeList = document.querySelectorAll('[data-mrp-preview="date"]');
		var categoryNodeList = document.querySelectorAll('[data-mrp-preview="category"]');
		var excerptNodeList = document.querySelectorAll('[data-mrp-preview="excerpt"]');
		var buttonWrapList = document.querySelectorAll('[data-mrp-preview="buttonWrap"]');
		var imageWrapList = document.querySelectorAll('[data-mrp-preview-image-wrap]');
		var metaWrapList = document.querySelectorAll('[data-mrp-preview="metaWrap"]');
		var buttonTextList = document.querySelectorAll('[data-mrp-preview="buttonText"]');
		var titleNodeList = document.querySelectorAll('[data-mrp-preview="postTitle"]');

		setStyleVar(preview, '--mrp-columns-mobile', columnsMobile);
		setStyleVar(preview, '--mrp-columns-tablet', columnsTablet);
		setStyleVar(preview, '--mrp-columns-desktop', columnsDesktop);
		setStyleVar(preview, '--mrp-gap', gap + 'px');
		setStyleVar(preview, '--mrp-grid-justify', buildFlexAlign(getValue('blockAlign', 'left')));
		setStyleVar(preview, '--mrp-heading-spacing', getValue('headingSpacing', '20') + 'px');
		setStyleVar(preview, '--mrp-heading-color', getValue('sectionTitleColor', '#111827'));
		setStyleVar(preview, '--mrp-heading-size', getValue('sectionTitleSize', '28') + 'px');
		setStyleVar(preview, '--mrp-heading-title-gap', getValue('sectionTitleMarginBottom', '0') + 'px');
		setStyleVar(preview, '--mrp-heading-weight', getValue('sectionTitleWeight', '700'));
		setStyleVar(preview, '--mrp-heading-align', getValue('sectionTitleAlign', 'left'));
		setStyleVar(preview, '--mrp-subtitle-color', getValue('sectionSubtitleColor', '#4b5563'));
		setStyleVar(preview, '--mrp-subtitle-size', getValue('sectionSubtitleSize', '16') + 'px');
		setStyleVar(preview, '--mrp-subtitle-align', getValue('sectionSubtitleAlign', 'left'));
		setStyleVar(preview, '--mrp-card-bg', getValue('cardBackgroundColor', '#ffffff'));
		setStyleVar(preview, '--mrp-card-border-color', getValue('cardBorderColor', '#e5e7eb'));
		setStyleVar(preview, '--mrp-card-border-width', getValue('cardBorderWidth', '1') + 'px');
		setStyleVar(preview, '--mrp-card-radius', getValue('cardBorderRadius', '16') + 'px');
		setStyleVar(preview, '--mrp-card-padding', getValue('cardPadding', '20') + 'px');
		setStyleVar(preview, '--mrp-card-shadow', buildShadow(getValue('cardShadow', 'soft')));
		setStyleVar(preview, '--mrp-card-text-align', getValue('cardContentAlign', 'left'));
		setStyleVar(preview, '--mrp-image-radius', getValue('imageRadius', '12') + 'px');
		setStyleVar(preview, '--mrp-image-ratio', buildRatio(getValue('imageRatio', 'landscape')));
		setStyleVar(preview, '--mrp-image-height', imageHeight === '0' ? 'auto' : imageHeight + 'px');
		setStyleVar(preview, '--mrp-post-title-color', getValue('postTitleColor', '#111827'));
		setStyleVar(preview, '--mrp-post-title-size', getValue('postTitleSize', '20') + 'px');
		setStyleVar(preview, '--mrp-post-title-margin-bottom', getValue('postTitleMarginBottom', '12') + 'px');
		setStyleVar(preview, '--mrp-post-title-weight', getValue('postTitleWeight', '700'));
		setStyleVar(preview, '--mrp-post-title-align', getValue('postTitleAlign', 'left'));
		setStyleVar(preview, '--mrp-post-title-clamp', getValue('postTitleClamp', '3'));
		setStyleVar(preview, '--mrp-excerpt-color', getValue('excerptColor', '#4b5563'));
		setStyleVar(preview, '--mrp-excerpt-size', getValue('excerptSize', '15') + 'px');
		setStyleVar(preview, '--mrp-button-text-color', getValue('buttonTextColor', '#ffffff'));
		setStyleVar(preview, '--mrp-button-bg', getValue('buttonBackgroundColor', '#111827'));
		setStyleVar(preview, '--mrp-button-radius', getValue('buttonRadius', '999') + 'px');
		setStyleVar(preview, '--mrp-button-align', buildFlexAlign(getValue('buttonAlign', 'left')));

		buildHeadingTag(sectionTitleTag, sectionTitle);

		var subtitleNode = document.querySelector('[data-mrp-preview="sectionSubtitle"]');
		if (subtitleNode) {
			subtitleNode.textContent = sectionSubtitle;
			toggleVisibility(subtitleNode, sectionSubtitle.trim() !== '');
		}

		titleNodeList.forEach(function (node, index) {
			node.textContent = titleText + ' ' + (index + 1);
		});
		buttonTextList.forEach(function (node) {
			node.textContent = buttonText;
		});
		excerptNodeList.forEach(function (node) {
			node.textContent = excerptText;
			toggleVisibility(node, showExcerpt);
		});
		buttonWrapList.forEach(function (node) {
			toggleVisibility(node, showButton && buttonText.trim() !== '');
		});
		imageWrapList.forEach(function (node) {
			toggleVisibility(node, showImage);
		});
		dateNodeList.forEach(function (node) {
			toggleVisibility(node, showDate);
		});
		categoryNodeList.forEach(function (node) {
			toggleVisibility(node, showCategory);
		});
		metaWrapList.forEach(function (node) {
			toggleVisibility(node, showDate || showCategory);
		});

		document.querySelectorAll('.mrp-admin-preview-block .mrp-card').forEach(function (card) {
			card.classList.toggle('is-clickable', fullCardLink);
		});
		document.querySelectorAll('.mrp-admin-preview-block .mrp-card-link-overlay').forEach(function (overlay) {
			toggleVisibility(overlay, fullCardLink);
		});

		updatePreviewFrameWidth(parseInt(previewWidthControl ? previewWidthControl.value : '420', 10));
		updatePreviewCards(parseInt(previewCardsControl ? previewCardsControl.value : '1', 10));
	}

	function bind() {
		var form = document.querySelector('.mrp-admin-layout');
		if (!form) {
			return;
		}

		form.addEventListener('input', updatePreview);
		form.addEventListener('change', updatePreview);
		updatePreview();

		if (window.jQuery) {
			window.jQuery(document).on('change', '.wp-color-picker', updatePreview);
		}
	}

	document.addEventListener('DOMContentLoaded', bind);
}());
