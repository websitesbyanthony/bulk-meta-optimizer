jQuery(document).ready(function($) {
    // Track optimization state
    let isOptimizing = false;
    let currentPostId = null;

    // Handle individual optimize button clicks
    $('.aico-optimize-button').on('click', function(e) {
        e.preventDefault();
        
        if (isOptimizing) {
            console.log('Already optimizing a post, please wait...');
            return;
        }

        const $button = $(this);
        const postId = $button.data('post-id');
        const nonce = $button.data('nonce');
        
        if (!postId || !nonce) {
            console.error('Missing required data:', { postId, nonce });
            return;
        }

        console.log('Starting optimization for post ID:', postId);
        isOptimizing = true;
        currentPostId = postId;

        // Update button state
        $button.prop('disabled', true)
               .find('.spinner')
               .addClass('is-active');

        // Make the AJAX call
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aico_generate_content',
                post_id: postId,
                nonce: nonce
            },
            beforeSend: function() {
                console.log('Sending optimization request for post ID:', postId);
            },
            success: function(response) {
                console.log('Received response:', response);
                
                if (response.success) {
                    console.log('Optimization successful:', response.data);
                    // Update UI to show success
                    $button.removeClass('button-primary')
                           .addClass('button-disabled')
                           .text('Optimized')
                           .prop('disabled', true);
                    
                    // Update status column if it exists
                    const $statusCell = $button.closest('tr').find('.column-optimization_status');
                    if ($statusCell.length) {
                        $statusCell.html('<span class="aico-status-optimized">Optimized</span>');
                    }
                } else {
                    console.error('Optimization failed:', response.data);
                    // Show error message
                    $button.addClass('button-error')
                           .text('Error - Try Again');
                    
                    // Show error notification
                    if (response.data) {
                        alert('Error: ' + response.data);
                    } else {
                        alert('An unknown error occurred while optimizing the content.');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', { xhr, status, error });
                // Log detailed error information
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        console.error('Server error details:', errorData);
                    } catch (e) {
                        console.error('Raw server response:', xhr.responseText);
                    }
                }
                
                // Show error message
                $button.addClass('button-error')
                       .text('Error - Try Again');
                alert('Error: ' + (error || 'Failed to optimize content. Please try again.'));
            },
            complete: function() {
                console.log('Optimization request completed for post ID:', postId);
                // Reset button state
                $button.find('.spinner').removeClass('is-active');
                $button.prop('disabled', false);
                isOptimizing = false;
                currentPostId = null;
            }
        });
    });

    // Handle bulk optimize button
    $('.aico-bulk-optimize').on('click', function(e) {
        e.preventDefault();
        
        if (isOptimizing) {
            console.log('Already processing bulk optimization, please wait...');
            return;
        }

        const $button = $(this);
        const postIds = $button.data('post-ids').split(',');
        const nonce = $button.data('nonce');
        
        if (!postIds.length || !nonce) {
            console.error('Missing required data:', { postIds, nonce });
            return;
        }

        console.log('Starting bulk optimization for posts:', postIds);
        isOptimizing = true;

        // Update button state
        $button.prop('disabled', true)
               .find('.spinner')
               .addClass('is-active');

        // Initialize progress tracking
        let processed = 0;
        const total = postIds.length;
        
        // Create progress bar
        const $progress = $('<div class="aico-progress"><div class="aico-progress-bar"></div></div>');
        $button.after($progress);

        // Process posts sequentially
        function processNext() {
            if (processed >= total) {
                console.log('Bulk optimization completed');
                // Clean up
                $progress.remove();
                $button.prop('disabled', false)
                       .find('.spinner')
                       .removeClass('is-active');
                isOptimizing = false;
                return;
            }

            const postId = postIds[processed];
            console.log('Processing post ID:', postId);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aico_generate_content',
                    post_id: postId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('Response for post ID ' + postId + ':', response);
                    
                    // Update row status
                    const $row = $('.aico-optimize-button[data-post-id="' + postId + '"]').closest('tr');
                    if (response.success) {
                        $row.find('.aico-optimize-button')
                            .removeClass('button-primary')
                            .addClass('button-disabled')
                            .text('Optimized')
                            .prop('disabled', true);
                            
                        const $statusCell = $row.find('.column-optimization_status');
                        if ($statusCell.length) {
                            $statusCell.html('<span class="aico-status-optimized">Optimized</span>');
                        }
                    } else {
                        console.error('Failed to optimize post ID ' + postId + ':', response.data);
                        $row.find('.aico-optimize-button')
                            .addClass('button-error')
                            .text('Error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error for post ID ' + postId + ':', { xhr, status, error });
                    // Log detailed error information
                    if (xhr.responseText) {
                        try {
                            const errorData = JSON.parse(xhr.responseText);
                            console.error('Server error details:', errorData);
                        } catch (e) {
                            console.error('Raw server response:', xhr.responseText);
                        }
                    }
                    
                    // Update row to show error
                    const $row = $('.aico-optimize-button[data-post-id="' + postId + '"]').closest('tr');
                    $row.find('.aico-optimize-button')
                        .addClass('button-error')
                        .text('Error');
                },
                complete: function() {
                    processed++;
                    // Update progress bar
                    const percent = (processed / total) * 100;
                    $progress.find('.aico-progress-bar').css('width', percent + '%');
                    
                    // Process next post
                    processNext();
                }
            });
        }

        // Start processing
        processNext();
    });

    // Add some basic styles
    $('<style>')
        .text(`
            .aico-optimize.disabled {
                pointer-events: none;
                opacity: 0.7;
            }
            .aico-optimize.aico-success {
                color: #46b450;
            }
            .aico-optimize.aico-error {
                color: #dc3232;
            }
        `)
        .appendTo('head');
}); 