$(function () {
    const $form = $('.search-filters');
    const $results = $('#scholarship-results');
    const $pagination = $('#scholarship-pagination');
    const $status = $('#search-status');

    let currentPage = 1;
    let activeRequest = null;

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function formatAward(min, max) {
        if (min !== null && max !== null) {
            const low = Number(min);
            const high = Number(max);

            if (low === high) {
                return '$' + low.toLocaleString('en-US');
            }

            return '$' + low.toLocaleString('en-US') +
                ' – $' + high.toLocaleString('en-US');
        }

        if (min !== null) {
            return 'From $' + Number(min).toLocaleString('en-US');
        }

        if (max !== null) {
            return 'Up to $' + Number(max).toLocaleString('en-US');
        }

        return 'Not specified';
    }

    function renderResults(scholarships) {
        if (!scholarships.length) {
            $results.html('<p>No scholarships match your search.</p>');
            return;
        }

        let html = `
            <div class="results-container">
                <table class="results-table">
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

        scholarships.forEach(function (item) {
            const location = item.state_name ||
                item.national_or_state_local ||
                'Not specified';

            html += `
                <tr>
                    <td>${escapeHtml(item.scholarship_name)}</td>
                    <td>${escapeHtml(item.provider_name)}</td>
                    <td>${escapeHtml(location)}</td>
                    <td>${escapeHtml(formatAward(
                        item.award_amount_min,
                        item.award_amount_max
                    ))}</td>
                    <td>${escapeHtml(
                        item.application_deadline || 'Not specified'
                    )}</td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        $results.html(html);
    }

    function renderPagination(data) {
        if (data.totalPages <= 1) {
            $pagination.empty();
            return;
        }

        const $previous = $('<button>', {
            type: 'button',
            text: 'Previous',
            disabled: data.page <= 1
        });

        const $next = $('<button>', {
            type: 'button',
            text: 'Next',
            disabled: data.page >= data.totalPages
        });

        const $pageInfo = $('<span>').text(
            ' Page ' + data.page + ' of ' + data.totalPages + ' '
        );

        $previous.on('click', function () {
            loadScholarships(data.page - 1);
        });

        $next.on('click', function () {
            loadScholarships(data.page + 1);
        });

        $pagination.empty().append(
            $previous,
            $pageInfo,
            $next
        );
    }

    function loadScholarships(page) {
        currentPage = page;

        if (activeRequest) {
            activeRequest.abort();
        }

        const parameters = $form.serializeArray();
        parameters.push({
            name: 'page',
            value: currentPage
        });

        $status.text('Loading scholarships...');
        $pagination.empty();

        activeRequest = $.ajax({
            url: 'ajax/scholarship_results.php',
            method: 'GET',
            data: parameters,
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    $status.text('Unable to load scholarships.');
                    return;
                }

                currentPage = data.page;
                renderResults(data.scholarships);
                renderPagination(data);

                $status.text(
                    data.total + ' scholarship(s) found.'
                );
            },
            error: function (xhr, status) {
                if (status === 'abort') {
                    return;
                }

                $status.text('Unable to load scholarships.');
                $results.empty();
            }
        });
    }

    // AJAX search without a full page reload
    $form.on('submit', function (event) {
        event.preventDefault();
        loadScholarships(1);
    });

    // Automatically update results when filters change
    $form.find('select, input[type="checkbox"]').on(
        'change',
        function () {
            loadScholarships(1);
        }
    );

    // Load the initial search results
    loadScholarships(1);
});
