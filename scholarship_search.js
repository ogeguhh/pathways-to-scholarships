/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Integrated AJAX Search
 *
 * Items 1-3:
 * AJAX filtering, keyword search, sorting, and pagination.
 *
 * Items 4-6:
 * Expired scholarship filter, scholarship detail links,
 * desktop tables, and mobile scholarship cards.
 */

$(function () {
    'use strict';

    const form = $('#scholarshipSearchForm');
    const results = $('#scholarshipResults');

    let activeRequest = null;
    let currentPage = 1;

    // Stop if the expected elements do not exist.
    if (!form.length || !results.length) {
        return;
    }

    /**
     * Escape text before inserting it into HTML.
     */
    function escapeHtml(value) {
        return $('<div>')
            .text(value == null ? '' : String(value))
            .html();
    }

    /**
     * Format scholarship award amounts.
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
     * Display the correct scholarship location.
     */
    function formatLocation(item) {
        if (item.national_or_state_local === 'National') {
            return 'National';
        }

        return item.state_name || 'Not specified';
    }

    /**
     * Create a safe scholarship detail URL.
     */
    function scholarshipUrl(item) {
        const id = Number(item.scholarship_id);

        if (!Number.isSafeInteger(id) || id <= 0) {
            return null;
        }

        return 'scholarship.php?id=' + id;
    }

    /**
     * Render the desktop results table.
     */
    function renderDesktopTable(scholarships) {
        let html = `
            <div class="scholarship-desktop-results">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Scholarship</th>
                                <th scope="col">Provider</th>
                                <th scope="col">Location</th>
                                <th scope="col">Award</th>
                                <th scope="col">Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        scholarships.forEach(function (item) {
            const name = escapeHtml(item.scholarship_name);
            const provider = escapeHtml(item.provider_name);
            const location = escapeHtml(formatLocation(item));
            const award = escapeHtml(formatAward(item));
            const deadline = escapeHtml(
                item.application_deadline || 'Not specified'
            );

            const url = scholarshipUrl(item);

            const nameHtml = url
                ? `<a class="scholarship-link" href="${url}">
                       ${name}
                   </a>`
                : name;

            html += `
                <tr>
                    <td>${nameHtml}</td>
                    <td>${provider}</td>
                    <td>${location}</td>
                    <td>${award}</td>
                    <td>${deadline}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        return html;
    }

    /**
     * Render mobile scholarship cards.
     */
    function renderMobileCards(scholarships) {
        let html = '<div class="scholarship-mobile-results">';

        scholarships.forEach(function (item) {
            const name = escapeHtml(item.scholarship_name);
            const provider = escapeHtml(item.provider_name);
            const location = escapeHtml(formatLocation(item));
            const award = escapeHtml(formatAward(item));
            const deadline = escapeHtml(
                item.application_deadline || 'Not specified'
            );

            const url = scholarshipUrl(item);

            const nameHtml = url
                ? `<a class="scholarship-link" href="${url}">
                       ${name}
                   </a>`
                : name;

            const detailButton = url
                ? `<a
                       class="btn btn-outline-primary btn-sm"
                       href="${url}"
                   >
                       View Scholarship Details
                   </a>`
                : '';

            html += `
                <article class="scholarship-mobile-card">
                    <h3>${nameHtml}</h3>

                    <p>
                        <strong>Provider:</strong>
                        ${provider}
                    </p>

                    <p>
                        <strong>Location:</strong>
                        ${location}
                    </p>

                    <p>
                        <strong>Award:</strong>
                        ${award}
                    </p>

                    <p>
                        <strong>Deadline:</strong>
                        ${deadline}
                    </p>

                    ${detailButton}
                </article>
            `;
        });

        html += '</div>';

        return html;
    }

    /**
     * Render pagination links.
     */
    function renderPagination(data) {
        if (data.total_pages <= 1) {
            return '';
        }

        let html = `
            <nav aria-label="Scholarship results pages">
                <ul class="pagination justify-content-center mt-4 mb-0">
        `;

        if (data.page > 1) {
            html += `
                <li class="page-item">
                    <a
                        href="#"
                        class="page-link ajax-page"
                        data-page="${data.page - 1}"
                    >
                        Previous
                    </a>
                </li>
            `;
        }

        // Display a limited number of page links.
        const startPage = Math.max(1, data.page - 2);
        const endPage = Math.min(
            data.total_pages,
            data.page + 2
        );

        if (startPage > 1) {
            html += `
                <li class="page-item">
                    <a
                        href="#"
                        class="page-link ajax-page"
                        data-page="1"
                    >
                        1
                    </a>
                </li>
            `;

            if (startPage > 2) {
                html += `
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                `;
            }
        }

        for (let page = startPage; page <= endPage; page++) {
            const active = page === data.page;

            html += `
                <li class="page-item ${active ? 'active' : ''}">
                    <a
                        href="#"
                        class="page-link ajax-page"
                        data-page="${page}"
                        ${active ? 'aria-current="page"' : ''}
                    >
                        ${page}
                    </a>
                </li>
            `;
        }

        if (endPage < data.total_pages) {
            if (endPage < data.total_pages - 1) {
                html += `
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                `;
            }

            html += `
                <li class="page-item">
                    <a
                        href="#"
                        class="page-link ajax-page"
                        data-page="${data.total_pages}"
                    >
                        ${data.total_pages}
                    </a>
                </li>
            `;
        }

        if (data.page < data.total_pages) {
            html += `
                <li class="page-item">
                    <a
                        href="#"
                        class="page-link ajax-page"
                        data-page="${data.page + 1}"
                    >
                        Next
                    </a>
                </li>
            `;
        }

        html += '</ul></nav>';

        return html;
    }

    /**
     * Render all scholarship results.
     */
    function renderResults(data) {
        const scholarships = Array.isArray(data.scholarships)
            ? data.scholarships
            : [];

        let html = `
            <div class="card-body">
                <h2 class="h5">Scholarship Results</h2>

                <p class="text-muted">
                    ${data.total} scholarship(s) found.
                    Page ${data.page} of ${data.total_pages}.
                </p>
        `;

        if (scholarships.length === 0) {
            html += '<p>No scholarships match your search.</p>';
        } else {
            // Item 6: Responsive desktop and mobile layouts.
            html += renderDesktopTable(scholarships);
            html += renderMobileCards(scholarships);
        }

        html += renderPagination(data);
        html += '</div>';

        results.html(html);
    }

    /**
     * Search scholarships through AJAX.
     */
    function searchScholarships(page = 1) {
        currentPage = page;

        // Collect all filters, including the expired checkbox.
        const formData = form.serializeArray();

        // Remove any existing page parameter.
        const parameters = formData.filter(function (item) {
            return item.name !== 'page';
        });

        parameters.push({
            name: 'page',
            value: currentPage
        });

        // Stop a previous search request.
        if (activeRequest !== null) {
            activeRequest.abort();
        }

        results.attr('aria-busy', 'true');

        activeRequest = $.ajax({
            url: 'scholarship_search_ajax.php',
            method: 'GET',
            data: parameters,
            dataType: 'json',

            success: function (data) {
                if (
                    data.success === true &&
                    Array.isArray(data.scholarships)
                ) {
                    currentPage = Number(data.page);
                    renderResults(data);
                } else {
                    results.html(`
                        <div class="alert alert-danger" role="alert">
                            Unable to load scholarships.
                        </div>
                    `);
                }
            },

            error: function (xhr, status) {
                // Aborted requests are expected.
                if (status === 'abort') {
                    return;
                }

                let message =
                    'Scholarships cannot be loaded right now.';

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
                    activeRequest = null;
                }
            }
        });
    }

    /**
     * Submit the search form without reloading.
     */
    form.on('submit', function (event) {
        event.preventDefault();
        searchScholarships(1);
    });

    /**
     * Automatically update when filters change.
     *
     * Includes the expired scholarship checkbox.
     */
    form.on(
        'change',
        'select, input[type="date"], input[type="number"], input[type="checkbox"]',
        function () {
            searchScholarships(1);
        }
    );

    /**
     * Handle pagination links created by AJAX.
     */
    results.on('click', '.ajax-page', function (event) {
        event.preventDefault();

        const page = Number($(this).data('page'));

        if (Number.isInteger(page) && page >= 1) {
            searchScholarships(page);
        }
    });

});
