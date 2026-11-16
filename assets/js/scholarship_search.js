/*
 * Pathways to Scholarships
 * Iteration 5 — AJAX Scholarship Search
 *
 * Updates scholarship results without reloading the page.
 * The PHP search page remains responsible for filtering,
 * sorting, and pagination.
 */

$(function () {
    const $form = $('form[action="/pages/scholarships.php"]');
    const $results = $('#scholarshipResults');

    // Stop if the expected search elements are missing.
    if (!$form.length || !$results.length) {
        return;
    }

    // Keep track of the current request so older searches
    // cannot overwrite newer results.
    let currentRequest = null;

    function loadScholarships(url, updateHistory = true) {
        // Cancel the previous request if it is still running.
        if (currentRequest) {
            currentRequest.abort();
        }

        $results.attr('aria-busy', 'true');

        currentRequest = $.ajax({
            url: url,
            method: 'GET',
            dataType: 'html'
        });

        currentRequest.done(function (html) {
            // Parse the returned page and extract only its results.
            const $response = $('<div>').append($.parseHTML(html));
            const $newResults = $response.find('#scholarshipResults');

            if (!$newResults.length) {
                $results.html(
                    '<div class="alert alert-danger">' +
                    'Search results could not be loaded.' +
                    '</div>'
                );
                return;
            }

            $results.html($newResults.html());

            // Update the browser URL without reloading the page.
            if (updateHistory) {
                window.history.pushState({}, '', url);
            }
        });

        currentRequest.fail(function (xhr, status) {
            if (status === 'abort') {
                return;
            }

            $results.html(
                '<div class="alert alert-danger">' +
                'Search failed. Please try again.' +
                '</div>'
            );
        });

        currentRequest.always(function () {
            $results.attr('aria-busy', 'false');
        });
    }

    // Submit the selected filters through AJAX.
    $form.on('submit', function (event) {
        event.preventDefault();

        const url = this.action + '?' + $form.serialize();

        loadScholarships(url);
    });

    // Handle pagination links inside dynamically updated results.
    $results.on('click', '.pagination a', function (event) {
        event.preventDefault();

        loadScholarships(this.href);

        // Move focus toward the updated results.
        $results.trigger('focus');
    });

    // Restore search results when using browser Back or Forward.
    $(window).on('popstate', function () {
        loadScholarships(window.location.href, false);
    });
});
