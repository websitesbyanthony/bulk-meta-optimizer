/**
 * AI Content Optimizer Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Test API connection
        $('#aico-test-api, #aico-test-api-page').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $button.attr('id') === 'aico-test-api' ? $('#aico-test-result') : $('#aico-test-result-page');
            
            // Show loading message
            $button.prop('disabled', true);
            $result.html('<span class="aico-loading">' + aicoData.strings.generating + '</span>');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_test_api',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="aico-success">' + response.data + '</span>');
                    } else {
                        $result.html('<span class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<span class="aico-error">' + aicoData.strings.error + ' ' + error + '</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Generate content for a single post
        $('.aico-optimize').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const postId = $link.data('post-id');
            
            if (!postId) {
                return;
            }
            
            // Show loading message
            $link.text(aicoData.strings.generating);
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_generate_content',
                    post_id: postId,
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $link.text(aicoData.strings.success);
                        setTimeout(function() {
                            $link.text(aicoData.strings.optimize);
                        }, 2000);
                    } else {
                        $link.text(aicoData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $link.text(aicoData.strings.optimize);
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    $link.text(aicoData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $link.text(aicoData.strings.optimize);
                    }, 3000);
                }
            });
        });
        
        // Bulk optimize
        let bulkOptimizeRunning = false;
        let bulkOptimizePostIds = [];
        let bulkOptimizeCurrentIndex = 0;

        // Add bulk optimize button handler
        $('.aico-bulk-optimize').on('click', function(e) {
            e.preventDefault();
            if (bulkOptimizeRunning) {
                return;
            }
            const $button = $(this);
            const postIds = $button.data('post-ids') ? $button.data('post-ids').toString().split(',') : [];
            if (!postIds.length) {
                return;
            }
            if (!confirm(aicoData.strings.confirmBulk)) {
                return;
            }
            startBulkOptimize(postIds);
        });

        function startBulkOptimize(postIds) {
            if (bulkOptimizeRunning) {
                return;
            }
            // Create progress bar if it doesn't exist
            if ($('#aico-bulk-progress').length === 0) {
                $('h1.wp-heading-inline').after(
                    '<div id="aico-bulk-progress" class="aico-bulk-progress">' +
                    '<div class="aico-progress-bar"><div class="aico-progress"></div></div>' +
                    '<div class="aico-progress-text"></div>' +
                    '</div>'
                );
            }
            bulkOptimizeRunning = true;
            bulkOptimizePostIds = postIds;
            bulkOptimizeCurrentIndex = 0;
            processBulkOptimize();
        }
        function processBulkOptimize() {
            const total = bulkOptimizePostIds.length;
            const progress = Math.round((bulkOptimizeCurrentIndex / total) * 100);
            // Update progress bar
            $('#aico-bulk-progress .aico-progress').css('width', progress + '%');
            $('#aico-bulk-progress .aico-progress-text').text(aicoData.strings.processing + ' ' + bulkOptimizeCurrentIndex + ' / ' + total);
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_bulk_optimize',
                    post_ids: bulkOptimizePostIds,
                    current_index: bulkOptimizeCurrentIndex,
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.done) {
                            // All done
                            bulkOptimizeRunning = false;
                            $('#aico-bulk-progress .aico-progress').css('width', '100%');
                            $('#aico-bulk-progress .aico-progress-text').text(response.data.message);
                        } else {
                            // Continue with next post
                            bulkOptimizeCurrentIndex = response.data.current_index;
                            $('#aico-bulk-progress .aico-progress-text').text(response.data.message);
                            setTimeout(processBulkOptimize, 500);
                        }
                    } else {
                        // Error
                        bulkOptimizeRunning = false;
                        $('#aico-bulk-progress .aico-progress-text').text(aicoData.strings.error + ' ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    // Error
                    bulkOptimizeRunning = false;
                    $('#aico-bulk-progress .aico-progress-text').text(aicoData.strings.error + ' ' + error);
                }
            });
        }
        
        // Settings form submission
        $('.aico-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');
            const originalButtonText = $submitButton.text();
            
            // Store original button text if not already stored
            if (!$submitButton.data('original-text')) {
                $submitButton.data('original-text', originalButtonText);
            }
            
            // Show loading message
            $submitButton.prop('disabled', true).text(aicoData.strings.processing);
            
            // Send AJAX request
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $form.before('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                        
                        // Change button text to "Saved!" temporarily
                        $submitButton.text('Saved!');
                        
                        // Reset button text after 2 seconds
                        setTimeout(function() {
                            $submitButton.text($submitButton.data('original-text'));
                        }, 2000);
                    } else {
                        // Show error message
                        $form.before('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>');
                        $submitButton.text($submitButton.data('original-text'));
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $form.before('<div class="notice notice-error is-dismissible"><p>' + aicoData.strings.error + ' ' + error + '</p></div>');
                    $submitButton.text($submitButton.data('original-text'));
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        });
        
        // Manual license check
        $('#bmo-force-license-check').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#bmo-license-check-result');
            
            // Show loading message
            $button.prop('disabled', true);
            $result.html('<span class="aico-loading">Checking license...</span>');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmo_force_license_check',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="aico-success">' + response.data + '</span>');
                        // Reload page after 2 seconds to show updated license status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<span class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<span class="aico-error">' + aicoData.strings.error + ' ' + error + '</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Category meta description generation
        $('.aico-generate-category-meta').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const categoryId = $button.data('category-id');
            const categoryName = $button.data('category-name');
            const categoryDescription = $button.data('category-description');
            
            // Show loading message
            $button.prop('disabled', true).text(aicoData.strings.generating);
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_generate_category_meta',
                    category_id: categoryId,
                    category_name: categoryName,
                    category_description: categoryDescription,
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Save the generated meta description
                        $.ajax({
                            url: aicoData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'aico_save_category_meta',
                                category_id: categoryId,
                                meta_description: response.data.meta_description,
                                nonce: aicoData.nonce
                            },
                            success: function(saveResponse) {
                                if (saveResponse.success) {
                                    $button.text(aicoData.strings.success);
                                    // Reload page to show updated meta description
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    $button.text(aicoData.strings.error + ' ' + saveResponse.data);
                                    setTimeout(function() {
                                        $button.text('Regenerate');
                                    }, 3000);
                                }
                            },
                            error: function() {
                                $button.text(aicoData.strings.error);
                                setTimeout(function() {
                                    $button.text('Regenerate');
                                }, 3000);
                            }
                        });
                    } else {
                        $button.text(aicoData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $button.text('Regenerate');
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    $button.text(aicoData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $button.text('Regenerate');
                    }, 3000);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Bulk generate category meta descriptions
        $('#aico-bulk-generate-categories').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            
            if (!confirm('Are you sure you want to generate meta descriptions for all categories? This may take some time.')) {
                return;
            }
            
            // Show loading
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_bulk_generate_categories',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const results = response.data;
                        const successCount = results.success.length;
                        const errorCount = results.error.length;
                        
                        let message = `Generated ${successCount} meta descriptions successfully.`;
                        if (errorCount > 0) {
                            message += ` ${errorCount} failed.`;
                        }
                        
                        alert(message);
                        
                        // Reload page to show updated meta descriptions
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Edit category meta description modal
        $('.aico-edit-category-meta').on('click', function(e) {
            e.preventDefault();
            
            const categoryId = $(this).data('category-id');
            const metaDescription = $(this).data('meta-description');
            
            $('#edit-category-id').val(categoryId);
            $('#edit-meta-description').val(metaDescription);
            
            // Add character counter if it doesn't exist
            if ($('#edit-meta-description').siblings('.aico-meta-counter').length === 0) {
                $('#edit-meta-description').after('<div class="aico-meta-counter">0 / 160 characters</div>');
            }
            
            // Update character counter
            updateMetaCounter();
            
            $('#aico-edit-meta-modal').show();
        });

        // Character counter function
        function updateMetaCounter() {
            const textarea = $('#edit-meta-description');
            const counter = textarea.siblings('.aico-meta-counter');
            const length = textarea.val().length;
            const maxLength = 160;
            
            counter.text(length + ' / ' + maxLength + ' characters');
            
            // Update counter color based on length
            counter.removeClass('warning error');
            if (length > maxLength) {
                counter.addClass('error');
            } else if (length > maxLength * 0.9) {
                counter.addClass('warning');
            }
        }

        // Update character counter on input
        $(document).on('input', '#edit-meta-description', function() {
            updateMetaCounter();
        });

        // Close modal
        $('.aico-modal-close, .aico-modal-cancel').on('click', function() {
            $('#aico-edit-meta-modal').hide();
        });

        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('aico-modal')) {
                $('.aico-modal').hide();
            }
        });

        // Save edited meta description
        $('#aico-edit-meta-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');
            const categoryId = $('#edit-category-id').val();
            const metaDescription = $('#edit-meta-description').val();
            
            // Show loading
            $submitButton.prop('disabled', true).text('Saving...');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_save_category_meta',
                    category_id: categoryId,
                    meta_description: metaDescription,
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $submitButton.text('Saved!');
                        setTimeout(function() {
                            $('#aico-edit-meta-modal').hide();
                            location.reload();
                        }, 1000);
                    } else {
                        $submitButton.text('Error: ' + response.data);
                        setTimeout(function() {
                            $submitButton.text('Save').prop('disabled', false);
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    $submitButton.text('Error: ' + error);
                    setTimeout(function() {
                        $submitButton.text('Save').prop('disabled', false);
                    }, 3000);
                }
            });
        });
        
        // Tab navigation
        $('.aico-tab-link').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const target = $link.attr('href');
            
            // Update active tab
            $('.aico-tab-link').removeClass('active');
            $link.addClass('active');
            
            // Show target tab content
            $('.aico-tab-content').removeClass('active');
            $(target).addClass('active');
            
            // Update URL hash without page jump
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        });
        
        // Check for hash in URL to activate correct tab
        if (window.location.hash) {
            const hash = window.location.hash;
            const $tabLink = $('.aico-tab-link[href="' + hash + '"]');
            
            if ($tabLink.length) {
                $tabLink.click();
            }
        }
        
        // Show/hide custom density input
        $('#aico-keyword-density').on('change', function() {
            const $select = $(this);
            const $container = $('#aico-custom-density-container');
            
            if ($select.val() === 'custom') {
                $container.show();
            } else {
                $container.hide();
            }
        });
        
        // Show/hide custom region input
        $('#aico-geographic-targeting').on('change', function() {
            const $select = $(this);
            const $container = $('#aico-custom-region-container');
            
            if ($select.val() === 'custom') {
                $container.show();
            } else {
                $container.hide();
            }
        });
        
        // Reset to defaults
        $('.aico-reset-defaults').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                const $button = $(this);
                const postType = $button.data('post-type');
                
                // Send AJAX request
                $.ajax({
                    url: aicoData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aico_reset_defaults',
                        post_type: postType,
                        nonce: aicoData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload page
                            window.location.reload();
                        } else {
                            alert(aicoData.strings.error + ' ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert(aicoData.strings.error + ' ' + error);
                    }
                });
            }
        });
        
        // Export settings
        $('#aico-export-settings').on('click', function(e) {
            e.preventDefault();
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_export_settings',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        const blob = new Blob([JSON.stringify(response.data)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'aico-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    } else {
                        alert(aicoData.strings.error + ' ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert(aicoData.strings.error + ' ' + error);
                }
            });
        });
        
        // Import settings
        $('#aico-import-settings').on('click', function(e) {
            e.preventDefault();
            
            const $fileInput = $('#aico-import-file');
            const file = $fileInput[0].files[0];
            
            if (!file) {
                alert('Please select a file to import.');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    // Send AJAX request
                    $.ajax({
                        url: aicoData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aico_import_settings',
                            settings: settings,
                            nonce: aicoData.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload page
                                window.location.reload();
                            } else {
                                alert(aicoData.strings.error + ' ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert(aicoData.strings.error + ' ' + error);
                        }
                    });
                } catch (error) {
                    alert('Invalid settings file: ' + error.message);
                }
            };
            reader.readAsText(file);
        });
        
        // Update range value display
        $('.aico-range').on('input', function() {
            const $range = $(this);
            const $value = $range.next('.aico-range-value');
            $value.text($range.val());
        });

        // Taxonomy selector functionality
        $('#taxonomy-selector').on('change', function() {
            const selectedTaxonomy = $(this).val();
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('taxonomy', selectedTaxonomy);
            window.location.href = currentUrl.toString();
        });

        // Taxonomy optimization on taxonomy management pages
        $('.aico-optimize-taxonomy').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const termId = $link.data('term-id');
            const nonce = $link.data('nonce');
            
            if (!termId) {
                return;
            }
            
            // Show loading message
            $link.text(aicoData.strings.generating);
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_generate_category_meta',
                    category_id: termId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $link.text(aicoData.strings.success);
                        setTimeout(function() {
                            $link.text('Optimize with AI');
                        }, 2000);
                    } else {
                        $link.text(aicoData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $link.text('Optimize with AI');
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    $link.text(aicoData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $link.text('Optimize with AI');
                    }, 3000);
                }
            });
        });

        // Optimize taxonomy term
        $('.aico-optimize-term').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const termId = $link.data('term-id');
            const termName = $link.data('term-name');
            
            if (!termId) {
                return;
            }
            
            // Show loading message
            $link.text(aicoData.strings.generating);
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_generate_category_meta',
                    category_id: termId,
                    category_name: termName,
                    category_description: '',
                    taxonomy: getCurrentTaxonomy(),
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $link.text(aicoData.strings.success);
                        setTimeout(function() {
                            $link.text(aicoData.strings.optimize);
                        }, 2000);
                    } else {
                        $link.text(aicoData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $link.text(aicoData.strings.optimize);
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    $link.text(aicoData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $link.text(aicoData.strings.optimize);
                    }, 3000);
                }
            });
        });

        // Helper function to get current taxonomy
        function getCurrentTaxonomy() {
            // Try to get taxonomy from URL
            const urlParams = new URLSearchParams(window.location.search);
            const taxonomy = urlParams.get('taxonomy');
            if (taxonomy) {
                return taxonomy;
            }
            
            // Try to get from page context
            const $taxonomyInput = $('input[name="taxonomy"]');
            if ($taxonomyInput.length) {
                return $taxonomyInput.val();
            }
            
            // Default to category
            return 'category';
        }
    });
})(jQuery);
