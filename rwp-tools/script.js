jQuery(document).ready(function($) {
    $('#delete-images-button').on('click', function(e) {
        if (!confirm('Are you sure you want to delete all images from trashed products? This action is irreversible.')) {
            e.preventDefault();
        }
    });
});
