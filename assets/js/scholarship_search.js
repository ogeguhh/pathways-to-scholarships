/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Bullet 3: AJAX Filtering
 *
 * Sends scholarship filters to PHP using jQuery.
 * Updates the results without reloading the page.
 * Also supports keyword search, sorting, and pagination.
 */

$(function () {

    // Find the existing search form and results container.
    const form = $('#scholarshipSearchForm');
    const results = $('#scholarshipResults');

    // Abort an older request when a new search starts.
    let activeRequest = null;

    // Keep track of the currently requested page.
    let currentPage = 1;

    /**
     * Escape database text before inserting it into HTML.
     * This prevents scholarship names or notes from being
     * interpreted as HTML code.
     */
    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    /**
     * Display award amounts in the results table.
     */
    function formatAward(item) {
        const min = item.award_amount_min;
        const max = item.award_amount_max;

        if (min !== null && max !== null) {
            if (Number(min) === Number(max)) {
                return '$' + Number(min).toFixed(2);
            }

            return '$' + Number(min).toFixed(2)
                + ' - $' + Number(max).toFixed(2);
        }

        if (min !== null) {
            return 'From $' + Number(min).toFixed(2);
        }

        if (max !== null) {
            return 'Up to $' + Number(max).toFixed(2);
        }

        return 'Not specified';
    }

    /**
     * Render the scholarship table and pagination links.
     */
    function renderResults(data) {

        let html = `
            <div class="card-body">
                <h2 class="h5">Scholarship Results</h2>

                <p class="text-muted">
                    ${data.total} scholarship(s) found.
                    Page ${data.page} of ${data.total_pages}.
                </p>
        `;

        if (data.scholarships.length === 0) {

            html += '<p>No scholarships match your search.</p>';

        } else {

            html += `
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Scholarship</th>
                                <th>Provider</th>
                                <th>Location</th>
                                <th>Award</th>
                                <th>Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.scholarships.forEach(function (item) {

                const location =
                    item.national_or_state_local === 'National'
                        ? 'National'
                        : (item.state_name || 'Not specified');

                html += `
                    <tr>
                        <td>${escapeHtml(item.scholarship_name)}</td>
                        <td>${escapeHtml(item.provider_name)}</td>
                        <td>${escapeHtml(location)}</td>
                        <td>${escapeHtml(formatAward(item))}</td>
                        <td>${escapeHtml(item.application_deadline || 'Not specified')}</td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
        }

        // Show pagination only when there is more than one page.
        if (data.total_pages > 1) {

            html += `
                <nav aria-label="Scholarship results pages">
                    <ul class="pagination justify-content-center mt-4 mb-0">
            `;

            if (data.page > 1) {
                html += `
                    <li class="page-item">
                        <a href="#" class="page-link ajax-page"
                           data-page="${data.page - 1}">
                            Previous
                        </a>
                    </li>
                `;
            }

            for (let page = 1; page <= data.total_pages; page++) {

                html += `
                    <li class="page-item ${page === data.page ? 'active' : ''}">
                        <a href="#" class="page-link ajax-page"
                           data-page="${page}"
                           ${page === data.page ? 'aria-current="page"' : ''}>
                            ${page}
                        </a>
                    </li>
                `;
            }

            if (data.page < data.total_pages) {
                html += `
                    <li class="page-item">
                        <a href="#" class="page-link ajax-page"
                           data-page="${data.page + 1}">
                            Next
                        </a>
                    </li>
                `;
            }

            html += '</ul></nav>';
        }

        html += '</div>';

        results.html(html);
    }

    /**
     * Send the current filters to the PHP endpoint.
     *
     * serializeArray() collects the named form fields.
     * $.ajax() sends them without refreshing the browser.
     */
    function searchScholarships(page = 1) {

        currentPage = page;

        const formData = form.serializeArray();

        formData.push({
            name: 'page',
            value: page
        });

        if (activeRequest !== null) {
            activeRequest.abort();
        }

        results.attr('aria-busy', 'true');

        activeRequest = $.ajax({
            url: 'scholarship_search_ajax.php',
            method: 'GET',
            data: formData,
            dataType: 'json',

            success: function (data) {

                if (data.success) {
                    currentPage = data.page;
                    renderResults(data);
                } else {
                    results.html(
                        '<div class="alert alert-danger">Unable to load scholarships.</div>'
                    );
                }
            },

            error: function (xhr, status) {

                // An aborted request is expected when filters change quickly.
                if (status === 'abort') {
                    return;
                }

                let message = 'Scholarships cannot be loaded right now.';

                if (
                    xhr.responseJSON &&
                    Array.isArray(xhr.responseJSON.errors)
                ) {
                    message = xhr.responseJSON.errors.join(' ');
                }

                results.empty().append(
                    $('<div>')
                        .addClass('alert alert-danger')
                        .attr('role', 'alert')
                        .text(message)
                );
            },

            complete: function (xhr, status) {
                if (status !== 'abort') {
                    results.attr('aria-busy', 'false');
                }
            }
        });
    }

    // Submit the search form through AJAX.
    form.on('submit', function (event) {
        event.preventDefault();
        searchScholarships(1);
    });

    // Automatically update results when dropdowns or dates change.
    form.on('change', 'select, input[type="date"], input[type="number"]', function () {
        searchScholarships(1);
    });

    // Keyword search runs when the user submits the form.
    // This avoids sending a request for every typed character.

    // Handle pagination links created by AJAX.
    results.on('click', '.ajax-page', function (event) {
        event.preventDefault();

        const page = Number($(this).data('page'));

        if (Number.isInteger(page) && page >= 1) {
            searchScholarships(page);
        }
    });

});
