/**
 * TemplateX Modal Component
 * 
 * Handles the template library modal UI and functionality
 * 
 * @package TemplateX
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * TemplateX Modal Class
     */
    class TemplateXModal {
        constructor() {
            this.templates = [];
            this.filteredTemplates = [];
            this.currentPreview = null;
            this.previewDevice = 'desktop';
            this.init();
        }

        /**
         * Initialize modal
         */
        init() {
            this.createModalHTML();
            this.bindEvents();
            this.loadTemplates();
        }

        /**
         * Create modal HTML structure
         */
        createModalHTML() {
            const modalHTML = `
                <div id="templatex-modal" class="templatex-modal">
                    <div class="templatex-modal-overlay"></div>
                    <div class="templatex-modal-container">
                        <div class="templatex-modal-header">
                            <h2 class="templatex-modal-title">
                                <img src="${templatexData.plugin_logo}" alt="">
                                <span>${templatexData.i18n.modalTitle}</span>
                            </h2>
							<div class="templatex-modal-tabs">
								<div class="templatex-tabs-left">
									<button class="templatex-tab active" data-tab="pages">Pages</button>
								</div>
							</div>
                            <div class="templatex-header-right">
                                <button class="templatex-modal-sync" aria-label="Sync" title="Sync Templates">
                                    <i class="eicon-sync"></i>
                                </button>
                                <button class="templatex-modal-close" aria-label="${templatexData.i18n.close}">
                                    <i class="eicon-close"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="templatex-modal-toolbar">
                            <div class="templatex-pages-count">
                                <span id="templatex-pages-count">0</span> Templates
                            </div>
                            <div class="templatex-modal-search">
                                <input 
                                    type="text" 
                                    id="templatex-search" 
                                    class="templatex-search-input" 
                                    placeholder="${templatexData.i18n.searchPlaceholder}"
                                />
								<i class="eicon-search"></i>
                            </div>
                        </div>

                        <div class="templatex-modal-body">
                            <div id="templatex-templates-grid" class="templatex-templates-grid">
                                <div class="templatex-loading">
                                    <i class="eicon-loading eicon-animation-spin"></i>
                                    <p>${templatexData.i18n.loading}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Modal -->
                <div id="templatex-preview-modal" class="templatex-preview-modal">
                    <div class="templatex-modal-overlay"></div>
                    <div class="templatex-preview-container">
                        <div class="templatex-preview-header">
                            <h3 class="templatex-preview-title"></h3>
                            <div class="templatex-preview-controls">
                                <div class="templatex-device-switcher">
                                    <button class="templatex-device-btn active" data-device="desktop" title="${templatexData.i18n.desktop}">
                                        <i class="eicon-device-desktop"></i>
                                    </button>
                                    <button class="templatex-device-btn" data-device="tablet" title="${templatexData.i18n.tablet}">
                                        <i class="eicon-device-tablet"></i>
                                    </button>
                                    <button class="templatex-device-btn" data-device="mobile" title="${templatexData.i18n.mobile}">
                                        <i class="eicon-device-mobile"></i>
                                    </button>
                                </div>
                                <button class="templatex-preview-insert" style="margin: 0 15px; background: #93003c; color: #fff; padding: 8px 20px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    <i class="eicon-file-download"></i> Insert
                                </button>
                                <button class="templatex-preview-close" aria-label="${templatexData.i18n.close}">
                                    <i class="eicon-close"></i>
                                </button>
                            </div>
                        </div>
                        <div class="templatex-preview-body">
                            <div class="templatex-preview-frame-wrapper" data-device="desktop">
                                <iframe class="templatex-preview-iframe" frameborder="0"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Append to body if not already present
            if (!$('#templatex-modal').length) {
                $('body').append(modalHTML);
            }
        }

        /**
         * Bind events
         */
        bindEvents() {
            const $modal = $('#templatex-modal');
            const $previewModal = $('#templatex-preview-modal');

            // Close main modal
            $modal.on('click', '.templatex-modal-close, .templatex-modal-overlay', () => {
                this.close();
            });

            // Close preview modal
            $previewModal.on('click', '.templatex-preview-close, .templatex-modal-overlay', () => {
                this.closePreview();
            });

            // Search functionality
            $('#templatex-search').on('input', (e) => {
                this.filterTemplates(e.target.value);
            });

            // Template card actions
            // Overlay click for preview
            $modal.on('click', '.templatex-template-overlay', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const templateId = $(e.currentTarget).closest('.templatex-template-card').data('template-id');
                this.openPreview(templateId);
            });

            // Preview button - open external link in new tab
            $modal.on('click', '.templatex-template-preview', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const templateId = $(e.currentTarget).closest('.templatex-template-card').data('template-id');
                this.openPreview(templateId);
            });

            // Sync button
            $modal.on('click', '.templatex-modal-sync', (e) => {
                e.preventDefault();
                const $icon = $(e.currentTarget).find('i');
                $icon.addClass('eicon-animation-spin');
                this.loadTemplates();
                setTimeout(() => $icon.removeClass('eicon-animation-spin'), 1000);
            });

            $modal.on('click', '.templatex-template-insert, .templatex-template-insert-overlay, .insert-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $card = $(e.currentTarget).closest('.templatex-template-card');
                const templateId = $card.data('template-id');
                const template = this.templates.find(t => t.id === templateId);
                
                if (template && template.type === 'preo') {
                    const proUrl = template.pro_url || 'https://wpkin.com/thank-redirect/pricing/';
                    window.open(proUrl, '_blank');
                } else if (template) {
                    this.insertTemplate(templateId);
                }
            });
            
            // Preview modal insert button
            $previewModal.on('click', '.templatex-preview-insert', (e) => {
                e.preventDefault();
                if (this.currentPreview) {
                    this.insertTemplate(this.currentPreview.id);
                    this.closePreview();
                }
            });

            // Device switcher
            $previewModal.on('click', '.templatex-device-btn', (e) => {
                const device = $(e.currentTarget).data('device');
                this.switchPreviewDevice(device);
            });

            // Prevent closing on container click
            $modal.on('click', '.templatex-modal-container', (e) => {
                e.stopPropagation();
            });

            $previewModal.on('click', '.templatex-preview-container', (e) => {
                e.stopPropagation();
            });

            // Close on ESC key
            $(document).on('keyup', (e) => {
                if (e.key === 'Escape') {
                    if ($previewModal.hasClass('active')) {
                        this.closePreview();
                    } else if ($modal.hasClass('active')) {
                        this.close();
                    }
                }
            });
        }

        /**
         * Load templates from API
         */
        loadTemplates() {
            $.ajax({
                url: templatexData.restUrl + '/templates',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', templatexData.nonce);
                },
                success: (response) => {
                    if (response.success) {
                        this.templates = response.data;
                        this.filteredTemplates = response.data;
                        this.renderTemplates();
                    }
                },
                error: () => {
                    this.showError();
                }
            });
        }

        /**
         * Render templates grid
         */
        renderTemplates() {
            const $grid = $('#templatex-templates-grid');
            const $count = $('#templatex-pages-count');
            
            $count.text(this.filteredTemplates.length);
            
            if (this.filteredTemplates.length === 0) {
                $grid.html(`
                    <div class="templatex-no-results">
                        <i class="eicon-ban"></i>
                        <p>${templatexData.i18n.noResults}</p>
                    </div>
                `);
                return;
            }

            let html = '';
            this.filteredTemplates.forEach(template => {
                const isPro = template.type === 'preo';
                const badgeClass = isPro ? 'templatex-badge-pro' : 'templatex-badge-free';
                const badgeText = isPro ? templatexData.i18n.pro : templatexData.i18n.free;
                const insertText = isPro ? 'Get Pro' : templatexData.i18n.insert;
                const insertIcon = isPro ? 'eicon-external-link-square' : 'eicon-file-download';
                
                html += `
                    <div class="templatex-template-card" data-template-id="${template.id}" data-template-type="${template.type}">
                        <div class="templatex-template-thumbnail">
                            <img src="${template.thumbnail}" alt="${template.name}">
                            <span class="templatex-template-badge ${badgeClass}">${badgeText}</span>
                            <div class="templatex-template-overlay">
                                <div class="templatex-overlay-icon">
                                    <i class="eicon-preview-medium"></i>
                                </div>
                            </div>
                            <div class="templatex-action-bar">
                                <button class="templatex-action-btn preview-btn templatex-template-preview" title="Open preview in new tab">
                                    <i class="eicon-preview-medium"></i>
                                    ${templatexData.i18n.preview}
                                </button>
                                <button class="templatex-action-btn insert-btn templatex-template-insert" title="${isPro ? 'View pricing' : 'Insert template'}">
                                    <i class="${insertIcon}"></i>
                                    ${insertText}
                                </button>
                            </div>
                        </div>
                        <div class="templatex-template-footer">
                            <h3 class="templatex-template-name">${template.name}</h3>
                        </div>
                    </div>
                `;
            });

            $grid.html(html);
        }

        /**
         * Filter templates by search term
         */
        filterTemplates(searchTerm) {
            searchTerm = searchTerm.toLowerCase().trim();
            
            if (!searchTerm) {
                this.filteredTemplates = this.templates;
            } else {
                this.filteredTemplates = this.templates.filter(template => {
                    return template.name.toLowerCase().includes(searchTerm) ||
                           template.id.toLowerCase().includes(searchTerm);
                });
            }

            this.renderTemplates();
        }

        /**
         * Open preview in new tab (external link)
         */
        openPreview(templateId) {
            const template = this.templates.find(t => t.id === templateId);
            if (!template) return;
            
            if (template.preview_url) {
                window.open(template.preview_url, '_blank');
            } else {
                alert('Preview URL not available for this template.');
            }
        }
        
        /**
         * Preview template (legacy - for modal preview if needed)
         */
        previewTemplate(templateId) {
            const template = this.templates.find(t => t.id === templateId);
            if (!template) return;

            this.currentPreview = template;
            
            // Update preview modal title
            $('#templatex-preview-modal .templatex-preview-title').text(template.name);
            
            // Show loading state
            const $iframe = $('#templatex-preview-modal .templatex-preview-iframe');
            $iframe.css('opacity', '0.5');
            
            // Load template data and render client-side with Elementor CSS
            $.ajax({
                url: templatexData.restUrl + '/templates/' + templateId,
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', templatexData.nonce);
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.renderTemplatePreview($iframe[0], response.data, template);
                    } else {
                        this.renderThumbnailPreview($iframe, template);
                    }
                    $iframe.css('opacity', '1');
                },
                error: () => {
                    this.renderThumbnailPreview($iframe, template);
                    $iframe.css('opacity', '1');
                }
            });
            
            // Show preview modal
            $('#templatex-preview-modal').addClass('active');
            $('body').addClass('templatex-preview-open');
        }
        
        /**
         * Render template preview in iframe
         */
        renderTemplatePreview(iframe, templateData, template) {
            // Generate HTML preview from template data
            const htmlContent = this.generatePreviewHTML(templateData, template);
            
            // Write to iframe
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(htmlContent);
            doc.close();
        }
        
        /**
         * Generate HTML preview from template data
         */
        generatePreviewHTML(templateData, template) {
            if (templateData && templateData.content && templateData.content.length > 0) {
                return this.renderTemplateContentWithCSS(templateData, template);
            }
            return this.renderThumbnailHTML(template);
        }
        
        /**
         * Render template content WITH Elementor CSS loaded
         */
        renderTemplateContentWithCSS(templateData, template) {
            let html = '';
            
            // Process each section in the template
            if (templateData.content && Array.isArray(templateData.content)) {
                templateData.content.forEach(section => {
                    html += this.renderSection(section);
                });
            }
            
            // Get site URL for loading CSS
            const siteUrl = templatexData.siteUrl || window.location.origin;
            
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>${template.name} - Preview</title>
                    
                    <!-- Google Fonts -->
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
                    
                    <!-- Font Awesome Icons -->
                    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' integrity='sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==' crossorigin='anonymous' referrerpolicy='no-referrer' />
                    
                    <!-- Elementor Frontend CSS -->
                    <link rel='stylesheet' href='${siteUrl}/wp-content/plugins/elementor/assets/css/frontend.min.css' type='text/css' media='all' />
                    <link rel='stylesheet' href='${siteUrl}/wp-content/plugins/elementor/assets/lib/eicons/css/elementor-icons.min.css' type='text/css' media='all' />
                    
                    <!-- WordPress/Theme Styles -->
                    <link rel='stylesheet' href='${siteUrl}/wp-includes/css/dist/block-library/style.min.css' type='text/css' media='all' />
                    
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
                        body { 
                            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            background: #fff;
                            overflow-x: hidden;
                        }
                        /* Elementor base styles */
                        .elementor-section {
                            width: 100%;
                            position: relative;
                        }
                        .elementor-container {
                            max-width: 1140px;
                            margin: 0 auto;
                            padding: 0 15px;
                            display: flex;
                            flex-wrap: wrap;
                        }
                        .elementor-section.full-width .elementor-container {
                            max-width: 100%;
                            padding: 0;
                        }
                        .elementor-row {
                            display: flex;
                            flex-wrap: wrap;
                            width: 100%;
                        }
                        .elementor-column {
                            min-height: 1px;
                            display: flex;
                            flex-direction: column;
                        }
                        .elementor-column-wrap {
                            width: 100%;
                            display: flex;
                            flex-direction: column;
                        }
                        .elementor-widget-wrap {
                            position: relative;
                            width: 100%;
                            flex-wrap: wrap;
                            align-content: flex-start;
                        }
                        .elementor-widget {
                            position: relative;
                            margin-bottom: 20px;
                        }
                        /* Better image handling */
                        img {
                            max-width: 100%;
                            height: auto;
                            display: block;
                        }
                        /* Button styling */
                        button, .elementor-button {
                            font-family: inherit;
                            transition: all 0.3s ease;
                            cursor: pointer;
                        }
                        /* Headings */
                        h1, h2, h3, h4, h5, h6 {
                            font-weight: 600;
                            line-height: 1.2;
                        }
                        /* Text alignment */
                        .text-center { text-align: center; }
                        .text-left { text-align: left; }
                        .text-right { text-align: right; }
                    </style>
                </head>
                <body class="elementor-page">
                    <div class="elementor elementor-${templateData.version || '0.4'}">
                        <div class="elementor-inner">
                            <div class="elementor-section-wrap">
                                ${html}
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `;
        }
        
        /**
         * Render actual template content
         */
        renderTemplateContent(templateData, template) {
            let html = '';
            
            // Process each section in the template
            if (templateData.content && Array.isArray(templateData.content)) {
                templateData.content.forEach(section => {
                    html += this.renderSection(section);
                });
            }
            
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>${template.name} - Preview</title>
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { 
                            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            background: #fff;
                        }
                        .elementor-section {
                            width: 100%;
                            position: relative;
                        }
                        .elementor-container {
                            max-width: 1140px;
                            margin: 0 auto;
                            padding: 0 15px;
                        }
                        .elementor-section[data-settings*="full_width"] .elementor-container,
                        .elementor-section.full-width .elementor-container {
                            max-width: 100%;
                            padding: 0;
                        }
                        .elementor-row {
                            display: flex;
                            flex-wrap: wrap;
                        }
                        .elementor-column {
                            padding: 15px;
                        }
                        .elementor-widget {
                            margin-bottom: 20px;
                        }
                        h1, h2, h3, h4, h5, h6 {
                            margin-bottom: 15px;
                            font-weight: 600;
                        }
                        h1 { font-size: 48px; }
                        h2 { font-size: 36px; }
                        h3 { font-size: 28px; }
                        p { margin-bottom: 15px; }
                        button, .elementor-button {
                            padding: 12px 30px;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: 600;
                            display: inline-block;
                            text-decoration: none;
                        }
                        img { max-width: 100%; height: auto; display: block; }
                        .text-center { text-align: center; }
                    </style>
                </head>
                <body>
                    ${html}
                </body>
                </html>
            `;
        }
        
        /**
         * Render a section
         */
        renderSection(section) {
            const settings = section.settings || {};
            
            // Background styling
            let bgStyle = '';
            if (settings.background_image && settings.background_image.url) {
                const bgImage = settings.background_image.url;
                const bgSize = settings.background_size || 'cover';
                const bgPosition = settings.background_position || 'center center';
                const bgRepeat = settings.background_repeat || 'no-repeat';
                bgStyle = `background-image: url(${bgImage}); background-size: ${bgSize}; background-position: ${bgPosition}; background-repeat: ${bgRepeat};`;
                
                // Background overlay
                if (settings.background_overlay_background === 'classic' && settings.background_overlay_color) {
                    const overlayOpacity = settings.background_overlay_opacity && settings.background_overlay_opacity.size ? settings.background_overlay_opacity.size : 0.5;
                    bgStyle += ` position: relative;`;
                }
            } else if (settings.background_background === 'gradient') {
                bgStyle = `background: linear-gradient(135deg, ${settings.background_color || '#93003c'}, ${settings.background_color_b || '#1c1e28'});`;
            } else if (settings.background_color) {
                bgStyle = `background-color: ${settings.background_color};`;
            }
            
            const minHeight = settings.height === 'min-height' && settings.custom_height ? 
                `min-height: ${settings.custom_height.size}${settings.custom_height.unit};` : 
                settings.height === 'full' ? 'min-height: 100vh;' : '';
                
            const padding = settings.padding ? 
                `padding: ${settings.padding.top || 0}${settings.padding.unit || 'px'} ${settings.padding.right || 0}${settings.padding.unit || 'px'} ${settings.padding.bottom || 0}${settings.padding.unit || 'px'} ${settings.padding.left || 0}${settings.padding.unit || 'px'};` : '';
            
            let columnsHTML = '';
            if (section.elements && Array.isArray(section.elements)) {
                section.elements.forEach(column => {
                    columnsHTML += this.renderColumn(column);
                });
            }
            
            // Background overlay HTML
            let overlayHTML = '';
            if (settings.background_overlay_background === 'classic' && settings.background_overlay_color) {
                const overlayOpacity = settings.background_overlay_opacity && settings.background_overlay_opacity.size ? settings.background_overlay_opacity.size : 0.5;
                const overlayColor = settings.background_overlay_color;
                // Convert hex to rgba
                const rgba = this.hexToRgba(overlayColor, overlayOpacity);
                overlayHTML = `<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: ${rgba}; z-index: 0;"></div>`;
            }
            
            // Check if full width layout
            const isFullWidth = settings.layout === 'full_width' || settings.stretch_section === 'section-stretched';
            const fullWidthClass = isFullWidth ? ' full-width' : '';
            
            // Margin
            let sectionMargin = '';
            if (settings.margin) {
                const m = settings.margin;
                sectionMargin = `margin: ${m.top || 0}${m.unit || 'px'} ${m.right || 0} ${m.bottom || 0}${m.unit || 'px'} ${m.left || 0};`;
            }
            
            return `
                <div class="elementor-section${fullWidthClass}" style="${bgStyle} ${minHeight} ${padding} ${sectionMargin} position: relative;">
                    ${overlayHTML}
                    <div class="elementor-container" style="position: relative; z-index: 1;">
                        <div class="elementor-row">
                            ${columnsHTML}
                        </div>
                    </div>
                </div>
            `;
        }
        
        /**
         * Convert hex color to rgba
         */
        hexToRgba(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        
        /**
         * Render a column
         */
        renderColumn(column) {
            const settings = column.settings || {};
            const width = settings._column_size || settings.width || '100%';
            
            // Background styling for column
            let bgStyle = '';
            if (settings.background_image && settings.background_image.url) {
                const bgImage = settings.background_image.url;
                const bgSize = settings.background_size || 'cover';
                const bgPosition = settings.background_position || 'center center';
                const bgRepeat = settings.background_repeat || 'no-repeat';
                bgStyle = `background-image: url(${bgImage}); background-size: ${bgSize}; background-position: ${bgPosition}; background-repeat: ${bgRepeat};`;
            } else if (settings.background_background === 'classic' && settings.background_color) {
                bgStyle = `background-color: ${settings.background_color};`;
            }
            
            const padding = settings.padding ? 
                `padding: ${settings.padding.top || 0}${settings.padding.unit || 'px'} ${settings.padding.right || 0}${settings.padding.unit || 'px'} ${settings.padding.bottom || 0}${settings.padding.unit || 'px'} ${settings.padding.left || 0}${settings.padding.unit || 'px'};` : '';
            
            let contentHTML = '';
            if (column.elements && Array.isArray(column.elements)) {
                column.elements.forEach(element => {
                    // Check if it's a nested section or a widget
                    if (element.elType === 'section') {
                        contentHTML += this.renderSection(element);
                    } else if (element.elType === 'widget') {
                        contentHTML += this.renderWidget(element);
                    }
                });
            }
            
            return `
                <div class="elementor-column" style="flex: 0 0 ${width}%; max-width: ${width}%; ${bgStyle} ${padding}">
                    ${contentHTML}
                </div>
            `;
        }
        
        /**
         * Render a widget
         */
        renderWidget(widget) {
            const settings = widget.settings || {};
            const widgetType = widget.widgetType || '';
            
            switch(widgetType) {
                case 'heading':
                    return this.renderHeading(settings);
                case 'text-editor':
                    return this.renderText(settings);
                case 'button':
                    return this.renderButton(settings);
                case 'image':
                    return this.renderImage(settings);
                case 'video':
                    return this.renderVideo(settings);
                case 'icon':
                    return this.renderIcon(settings);
                case 'icon-box':
                    return this.renderIconBox(settings);
                case 'divider':
                    return this.renderDivider(settings);
                case 'spacer':
                    return this.renderSpacer(settings);
                case 'ha-card':
                case 'ha-infobox':
                case 'ha-member':
                case 'ha-logo-grid':
                    return this.renderCustomWidget(widgetType, settings);
                default:
                    // Generic fallback for unknown widgets
                    return this.renderCustomWidget(widgetType, settings);
            }
        }
        
        /**
         * Render heading widget
         */
        renderHeading(settings) {
            const title = settings.title || '';
            const tag = settings.header_size || 'h2';
            const align = settings.align || 'left';
            const color = settings.title_color || '#000';
            
            // Typography settings
            const fontSize = settings.typography_font_size && settings.typography_font_size.size ? 
                `${settings.typography_font_size.size}${settings.typography_font_size.unit || 'px'}` : '';
            const fontFamily = settings.typography_font_family || '';
            const fontWeight = settings.typography_font_weight || '';
            const lineHeight = settings.typography_line_height && settings.typography_line_height.size ? 
                settings.typography_line_height.size + (settings.typography_line_height.unit || '') : '';
            
            // Padding
            let padding = '';
            if (settings._padding) {
                const p = settings._padding;
                padding = `padding: ${p.top || 0}${p.unit || 'px'} ${p.right || 0}${p.unit || 'px'} ${p.bottom || 0}${p.unit || 'px'} ${p.left || 0}${p.unit || 'px'};`;
            }
            
            // Margin
            let margin = '';
            if (settings._margin) {
                const m = settings._margin;
                margin = `margin: ${m.top || 0}${m.unit || 'px'} ${m.right || 0}${m.unit || 'px'} ${m.bottom || 0}${m.unit || 'px'} ${m.left || 0}${m.unit || 'px'};`;
            }
            
            let style = `color: ${color}; text-align: ${align};`;
            if (fontSize) style += ` font-size: ${fontSize};`;
            if (fontFamily) style += ` font-family: ${fontFamily};`;
            if (fontWeight) style += ` font-weight: ${fontWeight};`;
            if (lineHeight) style += ` line-height: ${lineHeight};`;
            style += ` ${padding} ${margin}`;
            
            return `<${tag} style="${style}">${title}</${tag}>`;
        }
        
        /**
         * Render text widget
         */
        renderText(settings) {
            const content = settings.editor || '';
            const align = settings.align || 'left';
            const color = settings.text_color || '#333';
            
            // Typography settings
            const fontSize = settings.typography_font_size && settings.typography_font_size.size ? 
                `${settings.typography_font_size.size}${settings.typography_font_size.unit || 'px'}` : '';
            const fontFamily = settings.typography_font_family || '';
            const fontWeight = settings.typography_font_weight || '';
            const lineHeight = settings.typography_line_height && settings.typography_line_height.size ? 
                settings.typography_line_height.size + (settings.typography_line_height.unit || '') : '';
            
            // Padding
            let padding = '';
            if (settings._padding) {
                const p = settings._padding;
                padding = `padding: ${p.top || 0}${p.unit || 'px'} ${p.right || 0}${p.unit || 'px'} ${p.bottom || 0}${p.unit || 'px'} ${p.left || 0}${p.unit || 'px'};`;
            }
            
            // Margin
            let margin = '';
            if (settings._margin) {
                const m = settings._margin;
                margin = `margin: ${m.top || 0}${m.unit || 'px'} ${m.right || 0}${m.unit || 'px'} ${m.bottom || 0}${m.unit || 'px'} ${m.left || 0}${m.unit || 'px'};`;
            }
            
            let style = `color: ${color}; text-align: ${align};`;
            if (fontSize) style += ` font-size: ${fontSize};`;
            if (fontFamily) style += ` font-family: ${fontFamily};`;
            if (fontWeight) style += ` font-weight: ${fontWeight};`;
            if (lineHeight) style += ` line-height: ${lineHeight};`;
            style += ` ${padding} ${margin}`;
            
            return `<div style="${style}">${content}</div>`;
        }
        
        /**
         * Render button widget
         */
        renderButton(settings) {
            const text = settings.text || 'Button';
            const align = settings.align || 'center';
            const bgColor = settings.background_color || settings.button_background_color || '#93003c';
            const textColor = settings.button_text_color || '#fff';
            
            // Border radius
            let borderRadius = '';
            if (settings.border_radius) {
                const br = settings.border_radius;
                borderRadius = `border-radius: ${br.top || 0}${br.unit || 'px'} ${br.right || 0}${br.unit || 'px'} ${br.bottom || 0}${br.unit || 'px'} ${br.left || 0}${br.unit || 'px'};`;
            }
            
            // Text padding (button size)
            let textPadding = 'padding: 15px 30px;';
            if (settings.text_padding) {
                const tp = settings.text_padding;
                textPadding = `padding: ${tp.top || 15}${tp.unit || 'px'} ${tp.right || 30}${tp.unit || 'px'} ${tp.bottom || 15}${tp.unit || 'px'} ${tp.left || 30}${tp.unit || 'px'};`;
            }
            
            // Typography
            const fontSize = settings.typography_font_size && settings.typography_font_size.size ? 
                `font-size: ${settings.typography_font_size.size}${settings.typography_font_size.unit || 'px'};` : '';
            const fontWeight = settings.typography_font_weight ? `font-weight: ${settings.typography_font_weight};` : '';
            
            const buttonStyle = `background: ${bgColor}; color: ${textColor}; ${borderRadius} ${textPadding} ${fontSize} ${fontWeight} border: none; cursor: pointer; display: inline-block;`;
            
            return `<div style="text-align: ${align};"><button style="${buttonStyle}">${text}</button></div>`;
        }
        
        /**
         * Render image widget
         */
        renderImage(settings) {
            const url = settings.image && settings.image.url ? settings.image.url : '';
            if (!url) return '';
            
            return `<img src="${url}" alt="" style="max-width: 100%; height: auto;">`;
        }
        
        /**
         * Render video widget
         */
        renderVideo(settings) {
            const imageOverlay = settings.image_overlay && settings.image_overlay.url ? settings.image_overlay.url : '';
            if (imageOverlay) {
                return `
                    <div style="position: relative; padding-bottom: 56.25%; background: #000;">
                        <img src="${imageOverlay}" alt="" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: rgba(255,255,255,0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                    </div>
                `;
            }
            return '<div style="background: #000; padding: 30px; text-align: center; color: #fff;">Video Content</div>';
        }
        
        /**
         * Render icon widget
         */
        renderIcon(settings) {
            const icon = settings.icon || {};
            const iconValue = icon.value || 'fas fa-star';
            const iconColor = settings.icon_color || settings.primary_color || '#000';
            const iconSize = settings.icon_size && settings.icon_size.size ? 
                settings.icon_size.size + (settings.icon_size.unit || 'px') : '50px';
            const align = settings.align || 'center';
            
            // Margin
            let margin = '';
            if (settings._margin) {
                const m = settings._margin;
                margin = `margin: ${m.top || 0}${m.unit || 'px'} ${m.right || 0}${m.unit || 'px'} ${m.bottom || 0}${m.unit || 'px'} ${m.left || 0}${m.unit || 'px'};`;
            }
            
            return `
                <div class="elementor-widget-icon" style="text-align: ${align}; ${margin}">
                    <i class="${iconValue}" style="color: ${iconColor}; font-size: ${iconSize};" aria-hidden="true"></i>
                </div>
            `;
        }
        
        /**
         * Render icon-box widget
         */
        renderIconBox(settings) {
            const icon = settings.icon || {};
            const iconValue = icon.value || 'fas fa-star';
            const iconColor = settings.icon_color || settings.primary_color || '#6366F1';
            const iconSize = settings.icon_size && settings.icon_size.size ? 
                settings.icon_size.size + (settings.icon_size.unit || 'px') : '48px';
            
            const title = settings.title_text || '';
            const titleColor = settings.title_color || '#1F2937';
            const description = settings.description_text || '';
            const descriptionColor = settings.description_color || '#6B7280';
            const position = settings.position || 'top';
            
            // Title typography
            const titleFontSize = settings.title_typography_font_size && settings.title_typography_font_size.size ? 
                `${settings.title_typography_font_size.size}${settings.title_typography_font_size.unit || 'px'}` : '20px';
            const titleFontFamily = settings.title_typography_font_family || 'inherit';
            const titleFontWeight = settings.title_typography_font_weight || '600';
            
            // Description typography
            const descFontSize = settings.description_typography_font_size && settings.description_typography_font_size.size ? 
                `${settings.description_typography_font_size.size}${settings.description_typography_font_size.unit || 'px'}` : '16px';
            const descFontFamily = settings.description_typography_font_family || 'inherit';
            const descLineHeight = settings.description_typography_line_height && settings.description_typography_line_height.size ? 
                settings.description_typography_line_height.size + (settings.description_typography_line_height.unit || '') : '1.6';
            
            // Padding
            let padding = 'padding: 20px;';
            if (settings._padding) {
                const p = settings._padding;
                padding = `padding: ${p.top || 20}${p.unit || 'px'} ${p.right || 20}${p.unit || 'px'} ${p.bottom || 20}${p.unit || 'px'} ${p.left || 20}${p.unit || 'px'};`;
            }
            
            return `
                <div class="elementor-widget-icon-box" style="${padding}">
                    <div class="elementor-icon-box-wrapper" style="text-align: ${position === 'left' || position === 'right' ? 'left' : 'center'};">
                        <div class="elementor-icon-box-icon" style="margin-bottom: 15px;">
                            <i class="${iconValue}" style="color: ${iconColor}; font-size: ${iconSize};" aria-hidden="true"></i>
                        </div>
                        ${title ? `<h3 class="elementor-icon-box-title" style="color: ${titleColor}; font-size: ${titleFontSize}; font-family: ${titleFontFamily}; font-weight: ${titleFontWeight}; margin-bottom: 10px;">${title}</h3>` : ''}
                        ${description ? `<p class="elementor-icon-box-description" style="color: ${descriptionColor}; font-size: ${descFontSize}; font-family: ${descFontFamily}; line-height: ${descLineHeight}; margin: 0;">${description}</p>` : ''}
                    </div>
                </div>
            `;
        }
        
        /**
         * Render divider widget
         */
        renderDivider(settings) {
            const color = settings.color || '#E5E7EB';
            const weight = settings.weight && settings.weight.size ? 
                settings.weight.size + (settings.weight.unit || 'px') : '1px';
            const width = settings.width && settings.width.size ? 
                settings.width.size + (settings.width.unit || '%') : '100%';
            const align = settings.align || 'center';
            const gap = settings.gap && settings.gap.size ? 
                settings.gap.size + (settings.gap.unit || 'px') : '20px';
            
            let alignStyle = 'margin: 0 auto;';
            if (align === 'left') alignStyle = 'margin-left: 0; margin-right: auto;';
            if (align === 'right') alignStyle = 'margin-left: auto; margin-right: 0;';
            
            return `
                <div class="elementor-widget-divider" style="padding: ${gap} 0;">
                    <div class="elementor-divider">
                        <span class="elementor-divider-separator" style="display: block; border-top: ${weight} solid ${color}; width: ${width}; ${alignStyle}"></span>
                    </div>
                </div>
            `;
        }
        
        /**
         * Render spacer widget
         */
        renderSpacer(settings) {
            const space = settings.space && settings.space.size ? 
                settings.space.size + (settings.space.unit || 'px') : '50px';
            
            return `<div class="elementor-widget-spacer" style="height: ${space};"></div>`;
        }
        
        /**
         * Render custom/third-party widgets (generic fallback)
         */
        renderCustomWidget(widgetType, settings) {
            // Try to extract key content from settings
            const title = settings.title || '';
            const description = settings.description || settings.editor || '';
            const imageUrl = settings.image && settings.image.url ? settings.image.url : '';
            const icon = settings.icon || '';
            const iconColor = settings.icon_color || '#287DFE';
            const buttonText = settings.button_text || settings.text || '';
            const jobTitle = settings.job_title || ''; // for ha-member
            
            // Extract padding from settings
            let widgetPadding = 'padding: 20px;';
            if (settings._padding) {
                const p = settings._padding;
                widgetPadding = `padding: ${p.top || 20}${p.unit || 'px'} ${p.right || 20}${p.unit || 'px'} ${p.bottom || 20}${p.unit || 'px'} ${p.left || 20}${p.unit || 'px'};`;
            } else if (settings.content_padding) {
                const cp = settings.content_padding;
                widgetPadding = `padding: ${cp.top || 20}${cp.unit || 'px'} ${cp.right || 20}${cp.unit || 'px'} ${cp.bottom || 20}${cp.unit || 'px'} ${cp.left || 20}${cp.unit || 'px'};`;
            }
            
            let html = `<div class="elementor-widget" style="${widgetPadding} margin-bottom: 20px;">`;
            
            // Render image if available
            if (imageUrl) {
                let imgStyle = 'max-width: 100%; height: auto; display: block;';
                
                // Image border radius
                if (settings.image_border_radius) {
                    const ibr = settings.image_border_radius;
                    imgStyle += ` border-radius: ${ibr.top || 0}${ibr.unit || 'px'};`;
                }
                
                // Image height
                if (settings.image_height && settings.image_height.size) {
                    imgStyle += ` height: ${settings.image_height.size}${settings.image_height.unit || 'px'}; object-fit: cover;`;
                }
                
                html += `<div style="margin-bottom: 15px;"><img src="${imageUrl}" alt="${title}" style="${imgStyle}"></div>`;
            }
            
            // Render icon if available and no image
            if (icon && !imageUrl) {
                const iconSize = settings.icon_size && settings.icon_size.size ? settings.icon_size.size : 40;
                html += `<div style="width: ${iconSize}px; height: ${iconSize}px; background: transparent; color: ${iconColor}; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; font-size: ${iconSize}px;">⚡</div>`;
            }
            
            // Render title with typography
            if (title) {
                const titleColor = settings.title_color || '#000';
                const titleFontSize = settings.title_typography_font_size && settings.title_typography_font_size.size ? 
                    `${settings.title_typography_font_size.size}${settings.title_typography_font_size.unit || 'px'}` : '24px';
                const titleFontWeight = settings.title_typography_font_weight || '700';
                const titleFontFamily = settings.title_typography_font_family || '';
                const titleLineHeight = settings.title_typography_line_height && settings.title_typography_line_height.size ? 
                    settings.title_typography_line_height.size + (settings.title_typography_line_height.unit || '') : '1.2';
                const titleSpacing = settings.title_spacing && settings.title_spacing.size ? 
                    `margin-bottom: ${settings.title_spacing.size}${settings.title_spacing.unit || 'px'};` : 'margin-bottom: 10px;';
                
                html += `<h3 style="color: ${titleColor}; font-size: ${titleFontSize}; font-weight: ${titleFontWeight}; ${titleFontFamily ? 'font-family: ' + titleFontFamily + ';' : ''} line-height: ${titleLineHeight}; ${titleSpacing}">${title}</h3>`;
            }
            
            // Render job title (for ha-member)
            if (jobTitle) {
                const jobTitleColor = settings.job_title_color || '#999';
                const jobTitleFontSize = settings.job_title_typography_font_size && settings.job_title_typography_font_size.size ? 
                    `${settings.job_title_typography_font_size.size}${settings.job_title_typography_font_size.unit || 'px'}` : '13px';
                html += `<p style="color: ${jobTitleColor}; font-size: ${jobTitleFontSize}; margin-bottom: 10px;">${jobTitle}</p>`;
            }
            
            // Render description/content with typography
            if (description) {
                const descColor = settings.description_color || settings.text_color || '#666';
                const descFontSize = settings.description_typography_font_size && settings.description_typography_font_size.size ? 
                    `${settings.description_typography_font_size.size}${settings.description_typography_font_size.unit || 'px'}` : '16px';
                const descFontWeight = settings.description_typography_font_weight || '400';
                const descFontFamily = settings.description_typography_font_family || '';
                const descLineHeight = settings.description_typography_line_height && settings.description_typography_line_height.size ? 
                    settings.description_typography_line_height.size + (settings.description_typography_line_height.unit || '') : '1.6';
                const descSpacing = settings.description_spacing && settings.description_spacing.size ? 
                    `margin-bottom: ${settings.description_spacing.size}${settings.description_spacing.unit || 'px'};` : 'margin-bottom: 15px;';
                
                html += `<div style="color: ${descColor}; font-size: ${descFontSize}; font-weight: ${descFontWeight}; ${descFontFamily ? 'font-family: ' + descFontFamily + ';' : ''} line-height: ${descLineHeight}; ${descSpacing}">${description}</div>`;
            }
            
            // Render button
            if (buttonText) {
                const btnColor = settings.button_color || settings.button_text_color || '#287DFE';
                const btnBg = settings.button_bg_color || 'transparent';
                
                // Button border
                let btnBorder = 'border: none;';
                if (settings.button_border_width) {
                    const bbw = settings.button_border_width;
                    const borderColor = settings.button_border_color || '#287DFE';
                    btnBorder = `border: ${bbw.top || 1}px solid ${borderColor};`;
                }
                
                // Button typography
                const btnFontSize = settings.button_typography_font_size && settings.button_typography_font_size.size ? 
                    `${settings.button_typography_font_size.size}${settings.button_typography_font_size.unit || 'px'}` : '14px';
                const btnFontWeight = settings.button_typography_font_weight || '400';
                const btnFontFamily = settings.button_typography_font_family || '';
                
                html += `<div style="margin-top: 15px;"><button style="background: ${btnBg}; color: ${btnColor}; ${btnBorder} padding: 10px 20px; font-size: ${btnFontSize}; font-weight: ${btnFontWeight}; ${btnFontFamily ? 'font-family: ' + btnFontFamily + ';' : ''} cursor: pointer;">${buttonText}</button></div>`;
            }
            
            html += '</div>';
            return html;
        }
        
        /**
         * Render thumbnail HTML (most accurate preview)
         */
        renderThumbnailHTML(template) {
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>${template.name} - Preview</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        html, body { 
                            margin: 0;
                            padding: 0;
                            height: 100%;
                            overflow-y: auto;
                            overflow-x: hidden;
                            background: #fff;
                        }
                        .preview-container {
                            width: 100%;
                            min-height: 100%;
                        }
                        img {
                            width: 100%;
                            height: auto;
                            display: block;
                            margin: 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="preview-container">
                        <img src="${template.thumbnail}" alt="${template.name}">
                    </div>
                </body>
                </html>
            `;
        }
        
        /**
         * Render thumbnail preview (fallback)
         */
        renderThumbnailPreview($iframe, template) {
            const htmlContent = this.generatePreviewHTML({}, template);
            const iframe = $iframe[0];
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(htmlContent);
            doc.close();
        }

        /**
         * Close preview modal
         */
        closePreview() {
            $('#templatex-preview-modal').removeClass('active');
            $('body').removeClass('templatex-preview-open');
            this.currentPreview = null;
            this.previewDevice = 'desktop';
            $('.templatex-device-btn').removeClass('active');
            $('.templatex-device-btn[data-device="desktop"]').addClass('active');
            $('.templatex-preview-frame-wrapper').attr('data-device', 'desktop');
        }

        /**
         * Switch preview device
         */
        switchPreviewDevice(device) {
            this.previewDevice = device;
            $('.templatex-device-btn').removeClass('active');
            $(`.templatex-device-btn[data-device="${device}"]`).addClass('active');
            $('.templatex-preview-frame-wrapper').attr('data-device', device);
        }

        /**
         * Insert template into Elementor
         */
        insertTemplate(templateId) {
            const template = this.templates.find(t => t.id === templateId);
            if (!template) return;

            if (template.type === 'preo') {
                this.showProMessage();
                return;
            }

            const $buttons = $(`.templatex-template-card[data-template-id="${templateId}"] .templatex-template-insert, .templatex-template-card[data-template-id="${templateId}"] .templatex-template-insert-overlay`);
            const originalHTML = $buttons.first().html();
            $buttons.html('<i class="eicon-loading eicon-animation-spin"></i>').prop('disabled', true);

            $.ajax({
                url: templatexData.restUrl + '/templates/' + templateId,
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', templatexData.nonce);
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.insertIntoElementor(response.data);
                        this.close();
                        this.showSuccessMessage();
                    } else {
                        this.showErrorMessage();
                    }
                    $buttons.html(originalHTML).prop('disabled', false);
                },
                error: () => {
                    this.showErrorMessage();
                    $buttons.html(originalHTML).prop('disabled', false);
                }
            });
        }

        /**
         * Insert template data into Elementor editor
         */
        insertIntoElementor(templateData) {
            try {
                if (typeof $e === 'undefined') {
                    throw new Error('Elementor editor ($e) not available');
                }

                if (!window.elementor) {
                    throw new Error('Elementor (window.elementor) not loaded');
                }

                // Method 1: Try using $e.run with document/elements/import
                if (typeof $e.run === 'function') {
                    try {
                        $e.run('document/elements/import', {
                            data: templateData,
                            options: {}
                        });
                        return;
                    } catch (insertError) {
                        // Continue to next method
                    }
                }

                // Method 2: Try with document/elements/create
                if (typeof $e.run === 'function') {
                    try {
                        if (templateData.content && Array.isArray(templateData.content)) {
                            templateData.content.forEach(element => {
                                $e.run('document/elements/create', {
                                    model: element,
                                    container: elementor.getPreviewContainer(),
                                    options: {}
                                });
                            });
                            return;
                        }
                    } catch (createError) {
                        // Continue to next method
                    }
                }

                // Method 3: Direct paste from clipboard simulation
                if (window.elementor.channels && window.elementor.channels.data) {
                    try {
                        window.elementor.channels.data.reply('elements:paste:data', templateData);
                        $e.run('document/elements/paste', {
                            container: elementor.getPreviewContainer()
                        });
                        return;
                    } catch (pasteError) {
                        // All methods failed
                    }
                }

                throw new Error('All insert methods failed');

            } catch (error) {
                this.showErrorMessage();
            }
        }

        /**
         * Show pro upgrade message
         */
        showProMessage() {
            // Simple alert for now - can be enhanced with custom modal
            alert(templatexData.i18n.proMessage);
        }

        /**
         * Show success message
         */
        showSuccessMessage() {
            // You can implement a toast notification here
        }

        /**
         * Show error message
         */
        showErrorMessage() {
            alert(templatexData.i18n.insertError);
        }

        /**
         * Show loading error
         */
        showError() {
            $('#templatex-templates-grid').html(`
                <div class="templatex-error">
                    <i class="eicon-warning"></i>
                    <p>${templatexData.i18n.insertError}</p>
                </div>
            `);
        }

        /**
         * Open modal
         */
        open() {
            $('#templatex-modal').addClass('active');
            $('body').addClass('templatex-modal-open');
            $('#templatex-search').val('').focus();
            this.filteredTemplates = this.templates;
            this.renderTemplates();
        }

        /**
         * Close modal
         */
        close() {
            $('#templatex-modal').removeClass('active');
            $('body').removeClass('templatex-modal-open');
            $('#templatex-search').val('');
        }
    }

    // Initialize modal when DOM is ready
    $(document).ready(() => {
        window.TemplateXModal = new TemplateXModal();
    });

})(jQuery);

