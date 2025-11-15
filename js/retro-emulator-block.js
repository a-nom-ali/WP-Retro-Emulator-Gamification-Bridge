(function(wp) {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, ToggleControl } = wp.components;
    const romChoices = (window.wpGamifyEmulatorBlock && window.wpGamifyEmulatorBlock.roms) || [];

    const romOptions = [
        { label: __('Auto (first ROM)', 'wp-gamify-bridge'), value: '' },
        ...romChoices.map(function(rom) {
            return { label: rom.title, value: String(rom.slug || rom.id) };
        })
    ];

    registerBlockType('wp-gamify/retro-emulator', {
        title: __('Retro Emulator', 'wp-gamify-bridge'),
        icon: 'games',
        category: 'widgets',
        supports: {
            align: true,
        },
        attributes: {
            rom: { type: 'string', default: '' },
            touchToggle: { type: 'boolean', default: true },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;

            return (
                wp.element.createElement('div', { className: props.className },
                    wp.element.createElement('div', { className: 'wp-gamify-emulator-block-placeholder' },
                        wp.element.createElement('p', null, __('Retro Emulator Shortcode', 'wp-gamify-bridge')),
                        wp.element.createElement('p', { className: 'description' }, __('Select a ROM to preview in the editor.', 'wp-gamify-bridge'))
                    ),
                    wp.element.createElement(InspectorControls, null,
                        wp.element.createElement(PanelBody, { title: __('ROM Settings', 'wp-gamify-bridge'), initialOpen: true },
                            wp.element.createElement(SelectControl, {
                                label: __('Default ROM', 'wp-gamify-bridge'),
                                value: attributes.rom,
                                options: romOptions,
                                onChange: function(value) { setAttributes({ rom: value }); }
                            }),
                            wp.element.createElement(ToggleControl, {
                                label: __('Show touch toggle button', 'wp-gamify-bridge'),
                                checked: attributes.touchToggle,
                                onChange: function(value) { setAttributes({ touchToggle: value }); }
                            })
                        )
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp || {});
