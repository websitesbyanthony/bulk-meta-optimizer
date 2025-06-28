(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        
        // Individual taxonomy optimization
        $('.aico-optimize-taxonomy').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const termId = $link.data('term-id');
            const taxonomy = $link.data('taxonomy');
            const termName = $link.data('term-name');
            const ajaxAction = $link.data('ajax-action');
            const nonce = $link.data('nonce');
            
            if (!termId) {
                console.error('No term ID found');
                return;
            }
            
            if (!confirm('Generate AI meta description for "' + termName + '"?')) {
                return;
            }
            
            // Show loading message
            $link.text(aicoTaxonomyData.strings.optimizing);
            
            // Get post type from URL or default to 'post'
            let postType = 'post';
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('post_type')) {
                postType = urlParams.get('post_type');
            } else if (taxonomy === 'product_cat' || taxonomy === 'product_tag') {
                postType = 'product';
            }
            
            // Prepare data based on taxonomy type
            const ajaxData = {
                action: ajaxAction,
                term_id: termId,
                taxonomy: taxonomy,
                post_type: postType,
                nonce: nonce
            };
            
            // Add specific ID parameter based on taxonomy type
            if (taxonomy === 'category' || taxonomy === 'product_cat') {
                ajaxData.category_id = termId;
            } else if (taxonomy === 'post_tag' || taxonomy === 'product_tag') {
                ajaxData.tag_id = termId;
            }
            
            console.log('Sending AJAX request:', ajaxData);
            
            // Send AJAX request
            $.ajax({
                url: aicoTaxonomyData.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        $link.text(aicoTaxonomyData.strings.success);
                        
                        // Update the description in the table if it exists
                        const $row = $link.closest('tr');
                        const $descriptionCell = $row.find('td:nth-child(2)'); // Description column
                        if ($descriptionCell.length && response.data.description) {
                            $descriptionCell.text(response.data.description);
                        }
                        
                        setTimeout(function() {
                            $link.text('Optimize with AI');
                        }, 2000);
                    } else {
                        $link.text(aicoTaxonomyData.strings.error + ' ' + response.data);
                        setTimeout(function() {
                            $link.text('Optimize with AI');
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr, status, error);
                    $link.text(aicoTaxonomyData.strings.error + ' ' + error);
                    setTimeout(function() {
                        $link.text('Optimize with AI');
                    }, 3000);
                }
            });
        });
        
        // Handle bulk optimization notice
        if (window.location.search.includes('aico_bulk_taxonomy_notice=1')) {
            // Show bulk optimization progress
            showBulkTaxonomyProgress();
        }
        
        function showBulkTaxonomyProgress() {
            // Create progress bar
            const $progressBar = $('<div class="aico-bulk-progress" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">' +
                '<h3>Bulk Taxonomy Optimization</h3>' +
                '<div class="aico-progress-bar" style="background: #e5e5e5; border-radius: 3px; height: 20px; margin: 10px 0; overflow: hidden;">' +
                '<div class="aico-progress" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s ease;"></div>' +
                '</div>' +
                '<div class="aico-progress-text" style="margin-top: 10px; font-weight: bold;">Starting bulk optimization...</div>' +
                '</div>');
            
            // Insert at the top of the page
            $('.wp-header-end').after($progressBar);
            
            // Start processing
            processBulkTaxonomyOptimization();
        }
        
        function processBulkTaxonomyOptimization() {
            // Get stored term IDs and taxonomy type
            $.ajax({
                url: aicoTaxonomyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aico_get_bulk_taxonomy_data',
                    nonce: aicoTaxonomyData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const termIds = response.data.term_ids;
                        const taxonomy = response.data.taxonomy;
                        
                        if (termIds && termIds.length > 0) {
                            processTaxonomyTerms(termIds, taxonomy, 0);
                        } else {
                            $('.aico-bulk-progress .aico-progress-text').text('No terms to process.');
                        }
                    } else {
                        $('.aico-bulk-progress .aico-progress-text').text('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $('.aico-bulk-progress .aico-progress-text').text('Error: ' + error);
                }
            });
        }
        
        function processTaxonomyTerms(termIds, taxonomy, currentIndex) {
            if (currentIndex >= termIds.length) {
                // All done
                $('.aico-bulk-progress .aico-progress').css('width', '100%');
                $('.aico-bulk-progress .aico-progress-text').text('Bulk optimization completed!');
                
                setTimeout(function() {
                    $('.aico-bulk-progress').fadeOut();
                    // Reload page to show updated descriptions
                    window.location.reload();
                }, 2000);
                return;
            }
            
            const termId = termIds[currentIndex];
            const progress = Math.round((currentIndex / termIds.length) * 100);
            
            // Update progress bar
            $('.aico-bulk-progress .aico-progress').css('width', progress + '%');
            $('.aico-bulk-progress .aico-progress-text').text('Processing term ' + (currentIndex + 1) + ' of ' + termIds.length);
            
            // Determine AJAX action based on taxonomy
            let ajaxAction = 'aico_optimize_category';
            if (taxonomy === 'post_tag' || taxonomy === 'product_tag') {
                ajaxAction = 'aico_optimize_tag';
            }
            
            // Get post type from URL or default based on taxonomy
            let postType = 'post';
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('post_type')) {
                postType = urlParams.get('post_type');
            } else if (taxonomy === 'product_cat' || taxonomy === 'product_tag') {
                postType = 'product';
            }
            
            // Process current term
            $.ajax({
                url: aicoTaxonomyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    term_id: termId,
                    taxonomy: taxonomy,
                    post_type: postType,
                    nonce: aicoTaxonomyData.nonce
                },
                success: function(response) {
                    // Process next term after a short delay
                    setTimeout(function() {
                        processTaxonomyTerms(termIds, taxonomy, currentIndex + 1);
                    }, 500);
                },
                error: function(xhr, status, error) {
                    // Continue with next term even if this one fails
                    setTimeout(function() {
                        processTaxonomyTerms(termIds, taxonomy, currentIndex + 1);
                    }, 500);
                }
            });
        }
    });
})(jQuery); 