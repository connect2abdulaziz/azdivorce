/**
 * TemplateX Elementor Integration
 * 
 * Adds TemplateX icon to Elementor editor and handles modal opening
 * 
 * @package TemplateX
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * TemplateX Elementor Integration Class
     */
    class TemplateXElementorIntegration {
        constructor() {
            this.hooked = false;
            this.init();
        }

        /**
         * Initialize integration
         */
        init() {
            if (window.elementor) {
                this.hookIntoElementor();
            }
            
            $(window).on('elementor:init', () => {
                if (!this.hooked) {
                    this.hookIntoElementor();
                }
            });
            
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                if (window.elementor && !this.hooked) {
                    clearInterval(checkInterval);
                    this.hookIntoElementor();
                } else if (checkCount > 50) {
                    clearInterval(checkInterval);
                }
            }, 200);
        }
        
        /**
         * Hook into Elementor once it's available
         */
        hookIntoElementor() {
            if (this.hooked) return;
            
            this.hooked = true;
            this.addTemplateXIcon();
        }

        /**
         * Add TemplateX icon to Elementor editor
         */
        addTemplateXIcon() {
            
            elementor.on('preview:loaded', () => {
                this.injectIconButton();
            });

            if (elementor.loaded) {
                this.injectIconButton();
            }
        }

        /**
         * Inject the TemplateX button into Elementor's UI
         */
        injectIconButton() {
            setTimeout(() => this.tryInjectButton(), 100);
            setTimeout(() => this.tryInjectButton(), 500);
            setTimeout(() => this.tryInjectButton(), 1000);
            setTimeout(() => this.tryInjectButton(), 2000);
        }
        
        /**
         * Attempt to inject button into add section area
         */
        tryInjectButton() {
            const previewFrame = $('#elementor-preview-iframe');
            
            if (!previewFrame.length) return;
            
            try {
                const iframeDoc = previewFrame[0].contentDocument || previewFrame[0].contentWindow.document;
                const $iframe = $(iframeDoc);
                const addSectionInner = $iframe.find('.elementor-add-section-inner');
                
                if (addSectionInner.length) {
                    let injectedCount = 0;
                    
                    addSectionInner.each((index, sectionInner) => {
                        const $sectionInner = $(sectionInner);
                        
                        if ($sectionInner.find('.templatex-library-button').length > 0) {
                            return;
                        }
                        
                        const templatexButton = this.createButton();
                        const folderButton = $sectionInner.find('.e-ai-layout-button');
                        
                        if (folderButton.length) {
                            folderButton.after(templatexButton);
                        } else {
                            $sectionInner.append(templatexButton);
                        }
                        
                        injectedCount++;
                    });
                    
                    if (injectedCount > 0) {
                        this.bindButtonClick(iframeDoc);
                    }
                }
            } catch (error) {
                // Silently fail
            }
        }

        /**
         * Create the TemplateX button HTML
         */
        createButton() {
            return `
                <div class="elementor-add-section-area-button templatex-library-button" title="${templatexData.i18n.modalTitle}" style="position: relative; display: inline-flex; background-color:#2878F3; padding:8px">
                    <div class="elementor-add-section-area-icon" style="position: relative;">
                       <img src="${templatexData.plugin_logo}" alt="">
                    </div>
                </div>
            `;
        }

        /**
         * Bind click event to button in iframe
         */
        bindButtonClick(doc = document) {
            $(doc).off('click', '.templatex-library-button');
            $(doc).on('click', '.templatex-library-button', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.openModal();
            });
        }

        /**
         * Open TemplateX modal
         */
        openModal() {
            if (window.TemplateXModal) {
                window.TemplateXModal.open();
            }
        }
    }

    // Initialize
    $(window).on('load', () => {
        new TemplateXElementorIntegration();
    });

})(jQuery);

