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
                        
                        // After 1.5 seconds, revert to original text
                        setTimeout(function() {
                            $submitButton.text($submitButton.data('original-text'));
                        }, 1500);
                    } else {
                        // Show error message
                        $form.before('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>');
                        
                        // Revert button text immediately
                        $submitButton.text($submitButton.data('original-text'));
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $form.before('<div class="notice notice-error is-dismissible"><p>' + aicoData.strings.error + ' ' + error + '</p></div>');
                    
                    // Revert button text immediately
                    $submitButton.text($submitButton.data('original-text'));
                },
                complete: function() {
                    // Re-enable the button
                    $submitButton.prop('disabled', false);
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
        
        // Reset defaults
        $('.aico-reset-defaults').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postType = $button.data('post-type');
            
            if (!confirm('Are you sure you want to reset all settings and prompts for this post type to default values?')) {
                return;
            }
            
            // Show loading message
            $button.prop('disabled', true).text('Resetting...');
            
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
                        // Reload the page to show updated settings
                        window.location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Reset to Defaults');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                    $button.prop('disabled', false).text('Reset to Defaults');
                }
            });
        });
        
        // Manual license check
        $('#bmo-manual-license-check').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#bmo-license-check-result');
            
            // Store original text
            const originalText = $button.text();
            
            // Show loading state
            $button.text('Checking...').prop('disabled', true);
            $result.html('<span class="aico-loading">Checking license status...</span>');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmo_manual_license_check',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let statusClass = 'aico-error';
                        let statusIcon = 'âŒ';
                        
                        if (data.status === 'success') {
                            statusClass = 'aico-success';
                            statusIcon = 'âœ”ï¸';
                        } else if (data.status === 'expired') {
                            statusClass = 'aico-warning';
                            statusIcon = 'âš ï¸';
                        }
                        
                        $result.html(
                            '<div class="' + statusClass + '">' +
                            '<strong>' + statusIcon + ' ' + data.message + '</strong><br>' +
                            '<small>Last checked: ' + data.last_check + '</small>' +
                            '</div>'
                        );
                        
                        // Reload page after 2 seconds to update the license status display
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        $result.html('<span class="aico-error">Error: ' + response.data + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<span class="aico-error">Error: ' + error + '</span>');
                },
                complete: function() {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
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

        });

        });

        });

        });

        });

        });

        // Character counters
        $('#aico-category-description').on('input', updateCategoryCounter);
        $('#aico-tag-description').on('input', updateTagCounter);
        
         else if (length > 150) {
                $counter.addClass('warning');
            }
        }
        
         else if (length > 150) {
                $counter.addClass('warning');
            }
        }

        });

        });

        });
        
        $('
        });
    });
})(jQuery);


