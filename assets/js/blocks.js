/* Native editor controls; all public rendering stays on the server. */
(function (wp) {
 'use strict';
 const el = wp.element.createElement;
 const { __ } = wp.i18n;
 const { InspectorControls, useBlockProps } = wp.blockEditor;
 const { PanelBody, TextControl, SelectControl, RangeControl, ToggleControl, Placeholder, Disabled } = wp.components;
 const ServerSideRender = wp.serverSideRender.ServerSideRender || wp.serverSideRender.default || wp.serverSideRender;
 const layouts = ['grid', 'masonry', 'slider', 'list', 'spotlight', 'compact'].map(value => ({ label: value.charAt(0).toUpperCase() + value.slice(1), value }));
 for (const block of abtBlocks.blocks) {
  wp.blocks.registerBlockType(block.name, {
   apiVersion: 3, title: block.title, description: block.description, category: 'widgets', icon: 'format-quote',
   attributes: block.attributes, supports: block.supports,
   edit({ attributes: a, setAttributes }) {
    const kind = block.name.split('/')[1];
    const set = key => value => setAttributes({ [key]: String(value) });
    const collectionControl = abtBlocks.collections.length
     ? el(SelectControl, { label: __('Collection', 'ashbi-testimonials'), value: a.collection, options: [{ label: __('Choose a collection', 'ashbi-testimonials'), value: '' }, ...abtBlocks.collections], onChange: set('collection') })
     : el(TextControl, { label: __('Collection slug or ID', 'ashbi-testimonials'), value: a.collection, onChange: set('collection'), help: __('Ask a testimonial manager for the collection slug.', 'ashbi-testimonials') });
    const controls = [collectionControl, el(TextControl, { key: 'product', label: __('Product/page ID', 'ashbi-testimonials'), value: a.product, onChange: set('product'), help: __('Optional. Use current for the current page or product.', 'ashbi-testimonials') })];
    if (kind !== 'review-form') controls.push(el(SelectControl, { key: 'preset', label: __('Design preset', 'ashbi-testimonials'), value: a.preset || '', options: [{ label: __('No preset', 'ashbi-testimonials'), value: '' }, ...(abtBlocks.presets || []).map(({label,value}) => ({label,value}))], onChange: value => setAttributes({preset:value,...((abtBlocks.presets || []).find(p => p.value === value)?.settings || {})}), help: __('Saved designs override layout, columns, theme and accent.', 'ashbi-testimonials') }));
    if (kind === 'testimonials') controls.push(
     el(SelectControl, { key: 'layout', disabled: !!a.preset, label: __('Layout', 'ashbi-testimonials'), value: a.layout, options: layouts, onChange: set('layout') }),
     el(RangeControl, { key: 'columns', disabled: !!a.preset, label: __('Columns', 'ashbi-testimonials'), value: Number(a.columns), min: 1, max: 4, onChange: set('columns') }),
     el(RangeControl, { key: 'limit', label: __('Reviews per page', 'ashbi-testimonials'), value: Number(a.limit), min: 1, max: 100, onChange: set('limit') }),
     ...['summary', 'load_more'].map(key => el(ToggleControl, { key, label: key === 'summary' ? __('Rating summary', 'ashbi-testimonials') : __('Load more reviews', 'ashbi-testimonials'), checked: a[key] === '1', onChange: value => setAttributes({ [key]: value ? '1' : '0' }) }))
    );
    if (kind === 'review-form') controls.push(
     el(TextControl, { key: 'title', label: __('Form title', 'ashbi-testimonials'), value: a.title, onChange: set('title') }),
     el(TextControl, { key: 'button', label: __('Button text', 'ashbi-testimonials'), value: a.button, onChange: set('button') })
    );
    if (kind !== 'review-form') controls.push(
     el(SelectControl, { key: 'theme', disabled: !!a.preset, label: __('Theme', 'ashbi-testimonials'), value: a.theme, options: [{ label: __('Light', 'ashbi-testimonials'), value: 'light' }, { label: __('Dark', 'ashbi-testimonials'), value: 'dark' }, { label: __('Automatic', 'ashbi-testimonials'), value: 'auto' }], onChange: set('theme') }),
     el(TextControl, { key: 'accent', disabled: !!a.preset, label: __('Accent colour', 'ashbi-testimonials'), value: a.accent, onChange: set('accent'), placeholder: '#2457ff', help: __('Use a hex colour; leave blank for the collection default.', 'ashbi-testimonials') })
    );
    return el('div', useBlockProps(),
     el(InspectorControls, null, el(PanelBody, { title: __('Review settings', 'ashbi-testimonials'), initialOpen: true }, controls)),
     !a.collection && !a.product
      ? el(Placeholder, { icon: 'format-quote', label: block.title, instructions: __('Choose a collection in the block settings to get started.', 'ashbi-testimonials') })
      : el(Disabled, null, el(ServerSideRender, { block: block.name, attributes: a, EmptyResponsePlaceholder: () => el(Placeholder, { label: block.title, instructions: __('No published reviews match this selection yet.', 'ashbi-testimonials') }) }))
    );
   },
   save() { return null; }
  });
 }
})(window.wp);
