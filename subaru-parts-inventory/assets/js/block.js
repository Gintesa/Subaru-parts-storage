/* Subaru Parts Inventory – "Subaru Parts Catalogue" block (no build step). */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;
	var cats = (window.SPSBlock && window.SPSBlock.categories) || [];

	wp.blocks.registerBlockType('subaru-parts/catalog', {
		apiVersion: 2,
		title: __('Subaru Parts Catalogue', 'subaru-parts-inventory'),
		description: __('Searchable catalogue of your Subaru parts inventory with category and condition filters.', 'subaru-parts-inventory'),
		icon: 'car',
		category: 'widgets',
		keywords: ['subaru', 'parts', 'inventory', 'catalog'],
		supports: { align: ['wide', 'full'], html: false },
		attributes: {
			category: { type: 'string', default: '' },
			condition: { type: 'string', default: '' },
			per_page: { type: 'number', default: 0 },
			view: { type: 'string', default: 'grid' },
			filters: { type: 'boolean', default: true },
			orderby: { type: 'string', default: 'newest' }
		},
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Catalogue settings', 'subaru-parts-inventory'), initialOpen: true },
						el(SelectControl, {
							label: __('Start with category', 'subaru-parts-inventory'),
							value: a.category,
							options: cats,
							onChange: function (v) { set({ category: v }); }
						}),
						el(SelectControl, {
							label: __('Condition', 'subaru-parts-inventory'),
							value: a.condition,
							options: [
								{ value: '', label: __('New & used', 'subaru-parts-inventory') },
								{ value: 'new', label: __('New only', 'subaru-parts-inventory') },
								{ value: 'used', label: __('Used only', 'subaru-parts-inventory') }
							],
							onChange: function (v) { set({ condition: v }); }
						}),
						el(SelectControl, {
							label: __('Layout', 'subaru-parts-inventory'),
							value: a.view,
							options: [
								{ value: 'grid', label: __('Cards with photos', 'subaru-parts-inventory') },
								{ value: 'table', label: __('Compact table', 'subaru-parts-inventory') }
							],
							onChange: function (v) { set({ view: v }); }
						}),
						el(SelectControl, {
							label: __('Sort', 'subaru-parts-inventory'),
							value: a.orderby,
							options: [
								{ value: 'newest', label: __('Newest first', 'subaru-parts-inventory') },
								{ value: 'part_number', label: __('Part number', 'subaru-parts-inventory') },
								{ value: 'name', label: __('Name', 'subaru-parts-inventory') }
							],
							onChange: function (v) { set({ orderby: v }); }
						}),
						el(RangeControl, {
							label: __('Parts per page (0 = default)', 'subaru-parts-inventory'),
							value: a.per_page,
							min: 0,
							max: 100,
							onChange: function (v) { set({ per_page: v || 0 }); }
						}),
						el(ToggleControl, {
							label: __('Show search & filters', 'subaru-parts-inventory'),
							checked: a.filters,
							onChange: function (v) { set({ filters: v }); }
						})
					)
				),
				el(ServerSideRender, { block: 'subaru-parts/catalog', attributes: a })
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp);
