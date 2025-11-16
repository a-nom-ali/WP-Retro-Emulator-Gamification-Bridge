(function(wp) {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, ToggleControl } = wp.components;
    const romChoices = (window.wpGamifyEmulatorBlock && window.wpGamifyEmulatorBlock.roms) || [];

    const romOptions = romChoices.map(function(rom) {
        return { label: rom.title, value: String(rom.slug || rom.id) };
    });

    registerBlockType('wp-gamify/rom-player', {
        title: __('ROM Player', 'wp-gamify-bridge'),
        description: __('Display a single ROM without a selector dropdown', 'wp-gamify-bridge'),
        icon: 'games',
        category: 'widgets',
        supports: {
            align: true,
        },
        attributes: {
            romId: { type: 'string', default: '' },
            touchToggle: { type: 'boolean', default: true },
            showMeta: { type: 'boolean', default: true },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const selectedRom = romChoices.find(function(rom) {
                return String(rom.slug || rom.id) === attributes.romId;
            });

            return (
                wp.element.createElement('div', { className: props.className },
                    wp.element.createElement('div', {
                        className: 'wp-gamify-rom-player-block-placeholder',
                        style: {
                            border: '1px dashed #ccc',
                            padding: '20px',
                            textAlign: 'center',
                            backgroundColor: '#f9f9f9'
                        }
                    },
                        wp.element.createElement('span', {
                            className: 'dashicons dashicons-games',
                            style: { fontSize: '48px', color: '#666', marginBottom: '10px' }
                        }),
                        wp.element.createElement('h3', null, __('ROM Player', 'wp-gamify-bridge')),
                        selectedRom
                            ? wp.element.createElement('p', { style: { fontWeight: 'bold' } }, selectedRom.title)
                            : wp.element.createElement('p', { style: { color: '#999' } }, __('No ROM selected', 'wp-gamify-bridge')),
                        wp.element.createElement('p', {
                            className: 'description',
                            style: { fontSize: '12px', color: '#666' }
                        }, __('Select a ROM in the sidebar settings →', 'wp-gamify-bridge'))
                    ),
                    wp.element.createElement(InspectorControls, null,
                        wp.element.createElement(PanelBody, { title: __('ROM Player Settings', 'wp-gamify-bridge'), initialOpen: true },
                            romOptions.length > 0
                                ? wp.element.createElement(SelectControl, {
                                    label: __('Select ROM', 'wp-gamify-bridge'),
                                    value: attributes.romId,
                                    options: [
                                        { label: __('-- Select a ROM --', 'wp-gamify-bridge'), value: '' },
                                        ...romOptions
                                    ],
                                    onChange: function(value) { setAttributes({ romId: value }); }
                                })
                                : wp.element.createElement('p', { style: { color: '#999' } },
                                    __('No ROMs available. Upload ROMs via ROM Library.', 'wp-gamify-bridge')
                                ),
                            wp.element.createElement(ToggleControl, {
                                label: __('Show touch toggle button', 'wp-gamify-bridge'),
                                checked: attributes.touchToggle,
                                onChange: function(value) { setAttributes({ touchToggle: value }); }
                            }),
                            wp.element.createElement(ToggleControl, {
                                label: __('Show ROM metadata', 'wp-gamify-bridge'),
                                checked: attributes.showMeta,
                                onChange: function(value) { setAttributes({ showMeta: value }); },
                                help: __('Display system, release year, publisher, and file size', 'wp-gamify-bridge')
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
