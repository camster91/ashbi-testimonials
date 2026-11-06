/* Native editor controls; all public rendering stays on the server. */
(function (wp) {
 'use strict';
 const el = wp.element.createElement;
 const { __ } = wp.i18n;
 const { InspectorControls, useBlockProps } = wp.blockEditor;
 const { Button, PanelBody, TextControl, SelectControl, RangeControl, ToggleControl, Placeholder, Disabled } = wp.components;
 const ServerSideRender = wp.serverSideRender.ServerSideRender || wp.serverSideRender.default || wp.serverSideRender;
 const layouts = [
  { label: __('Grid', 'ashbi-testimonials'), value: 'grid' },
  { label: __('Masonry', 'ashbi-testimonials'), value: 'masonry' },
  { label: __('Slider', 'ashbi-testimonials'), value: 'slider' },
  { label: __('List', 'ashbi-testimonials'), value: 'list' },
  { label: __('Spotlight', 'ashbi-testimonials'), value: 'spotlight' },
  { label: __('Compact', 'ashbi-testimonials'), value: 'compact' },
 ];
 for (const block of abtBlocks.blocks) {
  wp.blocks.registerBlockType(block.name, {
   apiVersion: 3, title: block.title, description: block.description, category: 'widgets', icon: 'format-quote',
   attributes: block.attributes, supports: block.supports,
   edit({ attributes: a, setAttributes }) {
    const kind = block.name.split('/')[1];
    const isForm = kind === 'review-form';
    const hasSource = a.collection || (!isForm && a.product);
    const set = key => value => setAttributes({ [key]: String(value) });
    const collections = abtBlocks.collections;
    const selectedCollection = collections.find(option => option.value === a.collection || option.slug === a.collection);
    const collectionOptions = [{ label: __('Choose a collection', 'ashbi-testimonials'), value: '' }, ...collections];
    if (a.collection && !selectedCollection) collectionOptions.push({ label: a.collection, value: a.collection });
    const collectionControl = collections.length
     ? el(SelectControl, { key: 'collection', label: __('Collection', 'ashbi-testimonials'), value: selectedCollection?.value || a.collection, options: collectionOptions, onChange: set('collection') })
     : el(TextControl, { key: 'collection', label: __('Collection slug or ID', 'ashbi-testimonials'), value: a.collection, onChange: set('collection'), help: __('Ask a testimonial manager for the collection slug.', 'ashbi-testimonials') });
    const scopeControls = [collectionControl, el(TextControl, { key: 'product', label: __('Product or page ID', 'ashbi-testimonials'), value: a.product, onChange: set('product'), help: __('Optional. Enter a post ID or use “current” for the page or product displaying this block.', 'ashbi-testimonials') })];
    const controls = [];
    if (kind !== 'review-form') controls.push(el(SelectControl, { key: 'preset', label: __('Design preset', 'ashbi-testimonials'), value: a.preset || '', options: [{ label: __('No preset', 'ashbi-testimonials'), value: '' }, ...(abtBlocks.presets || []).map(({label,value}) => ({label,value}))], onChange: value => setAttributes({preset:value,...((abtBlocks.presets || []).find(p => p.value === value)?.settings || {})}), help: __('Saved designs override layout, columns, theme and accent.', 'ashbi-testimonials') }));
    const effectiveLayout = (abtBlocks.presets || []).find(p => p.value === a.preset)?.settings?.layout || a.layout;
    if (kind === 'testimonials') controls.push(
     el(SelectControl, { key: 'layout', disabled: !!a.preset, label: __('Layout', 'ashbi-testimonials'), value: a.layout, options: layouts, onChange: set('layout') }),
     ...(!a.preset && !['list', 'spotlight', 'compact'].includes(effectiveLayout) ? [el(RangeControl, { key: 'columns', label: __('Columns', 'ashbi-testimonials'), value: Number(a.columns), min: 1, max: 4, onChange: set('columns') })] : []),
     el(RangeControl, { key: 'limit', label: __('Reviews per page', 'ashbi-testimonials'), value: Number(a.limit), min: 1, max: 100, onChange: set('limit') }),
     ...(['slider', 'spotlight'].includes(effectiveLayout) ? ['summary'] : ['summary', 'load_more']).map(key => el(ToggleControl, { key, label: key === 'summary' ? __('Rating summary', 'ashbi-testimonials') : __('Load more reviews', 'ashbi-testimonials'), checked: a[key] === '1', onChange: value => setAttributes({ [key]: value ? '1' : '0' }) }))
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
     el(InspectorControls, null,
      el(PanelBody, { title: __('Review source', 'ashbi-testimonials'), initialOpen: true }, scopeControls),
      el(PanelBody, { title: kind === 'review-form' ? __('Form text', 'ashbi-testimonials') : __('Appearance', 'ashbi-testimonials'), initialOpen: true }, controls)),
     !hasSource
      ? el(Placeholder, { icon: 'format-quote', label: block.title, instructions: isForm
       ? __('Choose where new reviews should go. Submissions will wait for approval.', 'ashbi-testimonials')
       : __('Choose a collection or use reviews for the current page or product.', 'ashbi-testimonials') },
       collectionControl,
       !isForm && el(Button, { variant: 'secondary', onClick: () => setAttributes({ product: 'current' }) }, __('Use current page or product', 'ashbi-testimonials')))
      : el(Disabled, null, el(ServerSideRender, { block: block.name, attributes: a, EmptyResponsePlaceholder: () => el(Placeholder, { label: block.title, instructions: __('No published reviews match this selection yet.', 'ashbi-testimonials') }) }))
    );
   },
   save() { return null; }
  });
 }
})(window.wp);
