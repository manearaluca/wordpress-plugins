jQuery(document).ready(function($) {
    $('#delete-images-button').click(function(e) {
        e.preventDefault();

        $('#my-plugin-message').html('Processing...');  // Initial message

        let totalProcessed = 0; // Counter to keep track of the total processed products

        function processBatch(page = 1) {
            $.post(rwp_tools.ajax_url, { action: 'delete_images', page: page }, function(response) {
                const batchProcessed = parseInt(response.data.actualdeletednumber, 10);
                totalProcessed += batchProcessed; // Update the counter

                // Display the batch message along with the total processed count
                $('#my-plugin-message').html(response.data.message + '<br>Total products processed so far: ' + totalProcessed);

                // If some products were processed in the current batch, continue to the next batch
                if (batchProcessed !== 0) {
                    processBatch(page + 1);
                } else {
                    $('#my-plugin-message').append('<br>All products processed!');
                }
            });
        }

        // Start the batch processing
        processBatch();
    });










    /**
     * In this approach, when the "Remove Unused Images" button is clicked, the script will keep calling
     * the server-side function until no more images are found to delete in a batch. The server-side function
     * (as provided) simply returns a random number between 0 and 50 to simulate the batch removal of images.
     * @type {number}
     */
    var page = 1;
    var totalChecked = 0;

    function removeUnusedImages() {
        var folderUrl = $('#folder-url').val();

        $.ajax({
            type: 'POST',
            url: rwp_tools.ajax_url,
            data: {
                action: 'rwp_remove_unused_images',
                page: page,
                'folder-url': folderUrl
            },
            success: function(response) {
                if(response.success) {
                    totalChecked += response.data.checked;
                    $('#rwp-remove-images-message').append('<p>' + response.data.message + '</p>');
                    $('#rwp-checked-images-count').text("Total images checked: " + totalChecked);

                    // Display removed image URLs
                    if(response.data.removed_images.length) {
                        $('#rwp-removed-images-list').append('<li>' + response.data.removed_images.join('</li><li>') + '</li>');
                    }

                    // If images were processed on the current page, call the function again with incremented page
                    if(response.data.processed) {
                        page++;
                        removeUnusedImages();
                    }
                }
            }
        });
    }

    $('#remove-unused-images-button').on('click', function(e) {
        e.preventDefault();
        $('#rwp-checked-images-count').text("");
        $('#rwp-removed-images-list').html("");
        removeUnusedImages();
    });



});
