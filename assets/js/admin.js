/**
 * AI Content Optimizer Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('AI Content Optimizer JS loaded');
        
        // Test API connection
        $('#aico-test-api, #aico-test-api-page').on('click', function(e) {
            e.preventDefault();
            console.log('Test API clicked');
            
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
                    console.log('API test response:', response);
                    if (response.success) {
                        $result.html('<span class="aico-success">' + response.data + '</span>');
                    } else {
                        $result.html('<span class="aico-error">' + aicoData.strings.error + ' ' + response.data + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('API test error:', error);
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
            console.log('Optimize clicked');
            
            const $link = $(this);
            const postId = $link.data('post-id');
            
            if (!postId) {
                console.error('No post ID found');
                return;
            }
            
            console.log('Optimizing post ID:', postId);
            
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
                    console.log('Optimize response:', response);
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
                    console.error('Optimize error:', error);
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
            console.log('Bulk optimize clicked');
            
            if (bulkOptimizeRunning) {
                return;
            }
            const $button = $(this);
            const postIds = $button.data('post-ids') ? $button.data('post-ids').toString().split(',') : [];
            if (!postIds.length) {
                console.error('No post IDs found for bulk optimize');
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
            console.log('Starting bulk optimize for:', postIds);
            
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
            console.log('Bulk optimize progress:', bulkOptimizeCurrentIndex, '/', total);
            
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
                    console.log('Bulk optimize response:', response);
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
                    console.error('Bulk optimize error:', error);
                    // Error
                    bulkOptimizeRunning = false;
                    $('#aico-bulk-progress .aico-progress-text').text(aicoData.strings.error + ' ' + error);
                }
            });
        }
        
        // Settings form submission
        $('.aico-settings-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Settings form submitted');
            
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
                    console.log('Settings save response:', response);
                    if (response.success) {
                        // Show success message
                        $form.before('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                        
                        // Change button text to "Saved!" temporarily
                        $submitButton.text('Saved!');
                        
                        // Reset button after 2 seconds
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
                    console.error('Settings save error:', error);
                    // Show error message
                    $form.before('<div class="notice notice-error is-dismissible"><p>' + aicoData.strings.error + ' ' + error + '</p></div>');
                    $submitButton.text($submitButton.data('original-text'));
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        });

        // Meta description character counter
        $('.aico-meta-description').on('input', function() {
            updateMetaCounter();
        });

        function updateMetaCounter() {
            $('.aico-meta-description').each(function() {
                const $textarea = $(this);
                const $counter = $textarea.next('.aico-meta-counter');
                const length = $textarea.val().length;
                
                if ($counter.length === 0) {
                    $textarea.after('<div class="aico-meta-counter">' + length + ' characters</div>');
                } else {
                    $counter.text(length + ' characters');
                }
                
                // Add warning/error classes
                $counter.removeClass('warning error');
                if (length > 160) {
                    $counter.addClass('error');
                } else if (length > 150) {
                    $counter.addClass('warning');
                }
            });
        }

        // Initialize meta counter on page load
        updateMetaCounter();

        // Export settings
        $('#aico-export-settings').on('click', function(e) {
            e.preventDefault();
            console.log('Export settings clicked');
            
            $.ajax({
                url: aicoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_export_settings',
                    nonce: aicoData.nonce
                },
                success: function(response) {
                    console.log('Export response:', response);
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
                    console.error('Export error:', error);
                    alert(aicoData.strings.error + ' ' + error);
                }
            });
        });
        
        // Import settings
        $('#aico-import-settings').on('click', function(e) {
            e.preventDefault();
            console.log('Import settings clicked');
            
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
                            console.log('Import response:', response);
                            if (response.success) {
                                // Reload page
                                window.location.reload();
                            } else {
                                alert(aicoData.strings.error + ' ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Import error:', error);
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
            console.log('Taxonomy optimize clicked');
            
            const $link = $(this);
            const termId = $link.data('term-id');
            const nonce = $link.data('nonce');
            
            if (!termId) {
                console.error('No term ID found');
                return;
            }
            
            console.log('Optimizing taxonomy term ID:', termId);
            
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
                    console.log('Taxonomy optimize response:', response);
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
                    console.error('Taxonomy optimize error:', error);
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
            console.log('Optimize term clicked');
            
            const $link = $(this);
            const termId = $link.data('term-id');
            const termName = $link.data('term-name');
            
            if (!termId) {
                console.error('No term ID found');
                return;
            }
            
            console.log('Optimizing term ID:', termId, 'Name:', termName);
            
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
                    console.log('Term optimize response:', response);
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
                    console.error('Term optimize error:', error);
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
        
        console.log('AI Content Optimizer JS initialization complete');
    });
})(jQuery);
