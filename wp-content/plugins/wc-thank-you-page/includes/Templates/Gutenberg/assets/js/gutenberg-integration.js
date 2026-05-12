/**
 * TemplateX Gutenberg Integration
 * 
 * Adds template library button to Gutenberg editor
 * 
 * @package TemplateX
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when Gutenberg is ready
    $(document).ready(function() {
        initTemplateXGutenberg();
    });

    /**
     * Initialize TemplateX Gutenberg integration
     */
    function initTemplateXGutenberg() {
        // Add button to Gutenberg toolbar
        addTemplateXButton();
        
        // Initialize modal
        initTemplateXModal();
    }

    /**
     * Add TemplateX button to Gutenberg toolbar
     */
    function addTemplateXButton() {
        // Wait for Gutenberg toolbar to be available
        const checkToolbar = setInterval(function() {
            const toolbar = document.querySelector('.editor-header__toolbar');
            
            if (toolbar) {
                clearInterval(checkToolbar);
                
                //Create div
				const thank_toolbar = document.createElement('div');
                thank_toolbar.className = 'toolbar-insert-layout-thank-redirect';

				// Create button
                const button = document.createElement('button');
                button.id = 'templatex-panel-button';
                button.className = 'components-button';
                button.setAttribute('aria-label', 'Thank Redirect Templates');
                
                // Add icon
                const icon = document.createElement('img');
                icon.src = templatexData.plugin_logo;
                icon.alt = 'Thank Redirect';
                icon.style.width = '24px';
                icon.style.height = '24px';
                
                // Add badge if needed
                const text = document.createElement('span');
                text.className = 'templatex-thank-redirect-text';
                text.textContent = 'ThankRedirect';
                
                // Append elements
                button.appendChild(icon);
                button.appendChild(text);
                thank_toolbar.appendChild(button);
                toolbar.appendChild(thank_toolbar);
                
                // Add click event
                button.addEventListener('click', openTemplateXModal);
            }
        }, 500);
    }

    /**
     * Open TemplateX modal
     */
    function openTemplateXModal() {
        // Show modal
        $('.templatex-modal').addClass('active');
        
        // Load templates if not already loaded
        if ($('.templatex-templates-grid').children().length === 0) {
            loadTemplates();
        }
    }

    /**
     * Initialize TemplateX modal
     */
    function initTemplateXModal() {
        // Create modal HTML - removed preview modal
        const modalHTML = `
            <div class="templatex-modal">
                <div class="templatex-modal-overlay"></div>
                <div class="templatex-modal-container">
                    <div class="templatex-modal-header">
                        <h3>
                            <img src="${templatexData.plugin_logo}" alt="Thank Redirect">
                            ${templatexData.i18n.modalTitle}
                        </h3>
                        <div class="templatex-header-actions">
                            <span class="templatex-sync-icon dashicons dashicons-update"></span>
                            <span class="templatex-close-modal dashicons dashicons-no-alt"></span>
                        </div>
                    </div>
                    <div class="templatex-modal-subheader">
                        <div class="templatex-templates-count">
                            <span class="count">0</span> Templates
                        </div>
                        <div class="templatex-templates-search">
                            <input type="text" placeholder="${templatexData.i18n.searchPlaceholder}">
							<span class="dashicons dashicons-search"></span>
                        </div>
                    </div>
                    <div class="templatex-modal-content">
                        <div class="templatex-templates-grid"></div>
                        <div class="templatex-templates-loading">${templatexData.i18n.loading}</div>
                        <div class="templatex-templates-empty" style="display: none;">${templatexData.i18n.noResults}</div>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body
        $('body').append(modalHTML);
        
        // Close modal on overlay click
        $('.templatex-modal-overlay').on('click', function() {
            $('.templatex-modal').removeClass('active');
        });
        
        // Close modal on close button click
        $('.templatex-close-modal').on('click', function() {
            $('.templatex-modal').removeClass('active');
        });
        
        // Search functionality
        $('.templatex-templates-search input').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('.templatex-template-item').each(function() {
                const name = $(this).find('.templatex-template-name').text().toLowerCase();
                
                if (name.indexOf(searchTerm) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Show/hide empty message
            if ($('.templatex-template-item:visible').length === 0) {
                $('.templatex-templates-empty').show();
            } else {
                $('.templatex-templates-empty').hide();
            }
        });
    }

    /**
     * Load templates from API
     */
    function loadTemplates() {
        // Show loading
        $('.templatex-templates-loading').show();
        $('.templatex-templates-empty').hide();
        
        // Get templates from API
        $.ajax({
            url: templatexData.restUrl + '/gutenberg-templates',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', templatexData.nonce);
            },
            success: function(response) {
                // Hide loading
                $('.templatex-templates-loading').hide();
                
                // Check if templates exist
                if (response.length === 0) {
                    $('.templatex-templates-empty').show();
                    return;
                }
                
                // Render templates
                renderTemplates(response);
            },
            error: function() {
                // Hide loading
                $('.templatex-templates-loading').hide();
                
                // Show empty message
                $('.templatex-templates-empty').show();
            }
        });
    }

    /**
     * Render templates
     * 
     * @param {Array} templates
     */
    function renderTemplates(templates) {
        // Clear grid
        $('.templatex-templates-grid').empty();
        
        // Update template count
        $('.templatex-templates-count .count').text(templates.length);
        
        // Loop through templates
        templates.forEach(function(template) {
            // Create template item
            const templateItem = $(`
                <div class="templatex-template-item" data-id="${template.id}" data-type="${template.type}">
                    ${template.type ? `<span class="templatex-pro-badge">${templatexData.i18n.pro}</span>` : ''}
                    <div class="templatex-template-thumbnail">
                        <img src="${template.thumbnail}" alt="${template.name}">
                        <div class="templatex-thumbnail-overlay">
                            <span class="dashicons dashicons-visibility"></span>
                        </div>
						<div class="templatex-template-actions">
                            <button class="templatex-preview-button">
                                <span class="dashicons dashicons-visibility"></span>
                                ${templatexData.i18n.preview}
                            </button>
                            ${template.type ? 
                                `<a href="${template.pro_url}" target="_blank" class="templatex-insert-button">
                                    <span class="dashicons dashicons-external"></span>
                                    <span>${templatexData.i18n.upgrade}</span>
                                </a>` : 
                                `<button class="templatex-insert-button">
                                    <span class="dashicons dashicons-download"></span>
                                    <span>${templatexData.i18n.insert}</span>
                                </button>`
                            }
                        </div>
                    </div>
                    <div class="templatex-template-info">
                        <h4 class="templatex-template-name">${template.name}</h4>
                    </div>
                </div>
            `);
            
            // Store the full template data in the DOM element
            templateItem.data('template', template);
            
            // Add to grid
            $('.templatex-templates-grid').append(templateItem);
            
            // Preview button click
            templateItem.find('.templatex-preview-button').on('click', function() {
                previewTemplate(template.id);
            });
            
            // Preview overlay click
            templateItem.find('.templatex-thumbnail-overlay').on('click', function() {
                previewTemplate(template.id);
            });
            
            // Insert button click (only for free templates)
            if (!template.type) {
                templateItem.find('.templatex-insert-button').on('click', function() {
                    insertTemplate(template.id);
                });
            }
        });
    }

    /**
     * Preview template - Opens the template's preview URL in a new tab
     * 
     * @param {string} templateId
     */
    function previewTemplate(templateId) {
        // Find the template with matching ID to get its preview_url
        const template = $('.templatex-template-item[data-id="' + templateId + '"]').data('template');
        
        // Use the template's preview_url if available, otherwise use a default URL
        const previewUrl = template && template.preview_url ? template.preview_url : 'https://wpkin.com/thank-redirect/thankyoupage';
        window.open(previewUrl, '_blank');
    }

    /**
     * Insert template
     * 
     * @param {string} templateId
     */
    function insertTemplate(templateId) {
        // Close modal
        $('.templatex-modal').removeClass('active');
        
        // Show loading message
        wp.data.dispatch('core/notices').createNotice(
            'info',
            templatexData.i18n.loading,
            {
                id: 'templatex-loading',
                isDismissible: false
            }
        );
        
        // Get template data
        $.ajax({
            url: templatexData.restUrl + '/gutenberg-templates/' + templateId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', templatexData.nonce);
            },
            success: function(response) {
                // Remove loading notice
                wp.data.dispatch('core/notices').removeNotice('templatex-loading');
                
                // Insert blocks
                if (response.content) {
                    try {
                        // Handle different content formats
                        let blocks;
                        
                        if (response.content.blocks) {
                            // If content has blocks property, use it directly
                            blocks = wp.blocks.parse(JSON.stringify(response.content.blocks));
                        } else if (typeof response.content === 'string') {
                            // If content is a string (HTML), parse it
                            blocks = wp.blocks.parse(response.content);
                        } else {
                            // Otherwise try to parse the entire content object
                            blocks = wp.blocks.parse(JSON.stringify(response.content));
                        }
                        
                        wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                        
                        // Show success message
                        wp.data.dispatch('core/notices').createNotice(
                            'success',
                            templatexData.i18n.insertSuccess,
                            {
                                type: 'snackbar',
                                isDismissible: true
                            }
                        );
                    } catch (error) {
                        console.error('Error parsing template content:', error);
                        
                        // Show error message
                        wp.data.dispatch('core/notices').createNotice(
                            'error',
                            templatexData.i18n.insertError + ': ' + error.message,
                            {
                                type: 'snackbar',
                                isDismissible: true
                            }
                        );
                    }
                } else {
                    // Show error message
                    wp.data.dispatch('core/notices').createNotice(
                        'error',
                        templatexData.i18n.insertError,
                        {
                            type: 'snackbar',
                            isDismissible: true
                        }
                    );
                }
            },
            error: function() {
                // Remove loading notice
                wp.data.dispatch('core/notices').removeNotice('templatex-loading');
                
                // Show error message
                wp.data.dispatch('core/notices').createNotice(
                    'error',
                    templatexData.i18n.insertError,
                    {
                        type: 'snackbar',
                        isDismissible: true
                    }
                );
            }
        });
    }

})(jQuery);