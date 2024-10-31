jQuery(document).ready(function () {
    window.Routeapp.analytics.send({
        action: 'render',
        event_category: 'thank-you-page-asset',
        event_label: 'thank-you-page',
        value: true
    });
});