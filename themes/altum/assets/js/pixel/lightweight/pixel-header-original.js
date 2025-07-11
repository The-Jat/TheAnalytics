let send_data_beacon = data => {
    try {
        let form_data = new FormData();
        form_data.append('data', JSON.stringify(data));

        navigator.sendBeacon(`${pixel_url_base}pixel-track/${pixel_key}`, form_data);
    } catch (error) {
        console.log(`Analytics pixel: ${error}`);
    }
};

class AltumCodeEvents {

    /* Create and initiate the class with the proper parameters */
    constructor() {

        /* Expose function to window */
        window[pixel_exposed_identifier] = {
            goal: (key) => {
                this.event_goal_conversion(key);
            }
        };

        /* Data */
        let url_params = new URLSearchParams(window.location.search);
        let query_parameters = new URL(document.location.toString()).searchParams.toString();

        let data = {
            path: window.location.pathname + (pixel_query_parameters_tracking_is_enabled && query_parameters ? '?' + query_parameters : ''),
            referrer: document.referrer.includes(`${location.protocol}//${location.host}${location.pathname}`) ? null : document.referrer,
            utm: {
                source: url_params.get('utm_source'),
                medium: url_params.get('utm_medium'),
                campaign: url_params.get('utm_campaign'),
            },
            resolution: {
                width: window.screen.width,
                height: window.screen.height
            },
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            theme: window?.matchMedia?.('(prefers-color-scheme:dark)')?.matches ? 'dark' : 'light'
        };

        /* Detect if unique or not */
        let referrer_url = document.createElement('a');
        referrer_url.href = document.referrer;
        let current_url = document.createElement('a');
        current_url.href = window.location.href;

        let type = document.referrer.trim() == '' || referrer_url.hostname != current_url.hostname ? 'landing_page' : 'pageview';

        /* Send the data to the server */
        send_data_beacon({
            type,
            url: window.location.href,
            data
        });

        /* Goals tracking if needed */
        if(pixel_goals.length) {
            let current_domain = get_current_url_domain_no_www();

            /* Iterate on all goals and initiate them if needed */
            for(let goal of pixel_goals) {

                /* Check if goal url matches the current url */
                if(goal.type == 'pageview' && (goal.url == current_domain || goal.url == 'www.'+current_domain)) {

                    this.event_goal_conversion(goal.key);

                }
            }
        }

    }

    event_goal_conversion(key) {

        /* Iterate on all goals and initiate them if needed */
        for(let goal of pixel_goals) {

            /* Check if goal url matches the current url */
            if(goal.key == key) {

                /* Send the goal completion */
                send_data_beacon({
                    type: 'goal_conversion',
                    url: window.location.href,
                    goal_key: goal.key
                });

                break;
            }

        }
    }

}
