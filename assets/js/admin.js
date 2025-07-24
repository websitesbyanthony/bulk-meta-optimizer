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
                        $link.removeClass('aico-error-state').text(aicoData.strings.success);
                        setTimeout(function() {
                            $link.text(aicoData.strings.optimize);
                        }, 2000);
                    } else {
                        $link.addClass('aico-error-state').html(aicoData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $link.removeClass('aico-error-state').text(aicoData.strings.optimize);
                        }, 5000);
                    }
                },
                error: function(xhr, status, error) {
                    $link.addClass('aico-error-state').html(aicoData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $link.removeClass('aico-error-state').text(aicoData.strings.optimize);
                    }, 5000);
                }
            });
        });
        
        // Bulk optimize - show confirmation and redirect
        $('.aico-bulk-optimize').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const postIds = $button.data('post-ids') ? $button.data('post-ids').toString().split(',') : [];
            if (!postIds.length) {
                alert('No items selected for bulk optimization.');
                return;
            }
            if (!confirm(aicoData.strings.confirmBulk)) {
                return;
            }
            // The bulk action will be handled by WordPress bulk actions
            // This is just for custom bulk buttons if needed
        });
        
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
        
        // Brand Profile functionality
        $('#aico-build-profile').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#aico-build-profile-result');
            
            // Show loading message
            $button.prop('disabled', true);
            $result.html('<div class="aico-loading">' + aicoData.strings.generating + '</div>');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_build_brand_profile',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="aico-success">' + response.data.message + '</div>');
                        // Reload page to show the edit form
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + error + '</div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Save brand profile form
        $('#aico-brand-profile-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');
            const $result = $('#aico-save-profile-result');
            const originalButtonText = $submitButton.text();
            
            // Show loading message
            $submitButton.prop('disabled', true).text(aicoData.strings.processing);
            $result.html('<div class="aico-loading">' + aicoData.strings.processing + '</div>');
            
            // Send AJAX request
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=aico_save_brand_profile',
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="aico-success">' + response.data + '</div>');
                        $submitButton.text('Saved!');
                        setTimeout(function() {
                            $submitButton.text(originalButtonText);
                        }, 2000);
                    } else {
                        $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</div>');
                        $submitButton.text(originalButtonText);
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + error + '</div>');
                    $submitButton.text(originalButtonText);
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        });
        
        // Rebuild brand profile
        $('#aico-rebuild-profile').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to rebuild your brand profile? This will replace your current profile with a new AI-generated one.')) {
                const $button = $(this);
                const $result = $('#aico-save-profile-result');
                
                // Show loading message
                $button.prop('disabled', true);
                $result.html('<div class="aico-loading">' + aicoData.strings.generating + '</div>');
                
                // Send AJAX request
                $.ajax({
                    url: aicoData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aico_build_brand_profile',
                        nonce: aicoData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="aico-success">' + response.data.message + '</div>');
                            // Reload page to show updated profile
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div class="aico-error">' + aicoData.strings.error + ' ' + error + '</div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            }
        });
    });
})(jQuery);
