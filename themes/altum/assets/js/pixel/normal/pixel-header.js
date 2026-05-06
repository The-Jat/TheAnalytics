/* Sending data via ES6 Fetch */
let send_data_fetch = async data => {
    try {
        data['url'] = window.location.href;

        let form_data = new FormData();
        form_data.append('data', JSON.stringify(data));

        let request = await fetch(`${pixel_url_base}pixel-track/${pixel_key}`, {
            method: 'POST',
            body: form_data
        })

        let response = await request.text()

        return response == '' ? response : JSON.parse(response);

    } catch (error) {
        console.log(`Analytics pixel: ${error}`);
    }
}

/* Sending data without expecting return answer */
let send_data_beacon = data => {
    try {
        data['url'] = window.location.href;

        let form_data = new FormData();
        form_data.append('data', JSON.stringify(data));

        navigator.sendBeacon(`${pixel_url_base}pixel-track/${pixel_key}`, form_data);
    } catch (error) {
        console.log(`Analytics pixel: ${error}`);
    }
};

/* Visitor class */
class AltumCodeVisitor {

    /* Create and initiate the class with the proper parameters */
    async initiate() {

        /* Check if we already have the visitor id */
        if(localStorage.getItem(get_dynamic_var('visitor_uuid')) && localStorage.getItem(get_dynamic_var('visitor_uuid')).trim() != '') {
            this.visitor_uuid = localStorage.getItem(get_dynamic_var('visitor_uuid')).trim();

            let custom_parameters = this.get_custom_parameters();

            /* If custom parameters are set after the initiation of the visitor, update the visitor with the new parameters */
            if(
                custom_parameters && (
                    !localStorage.getItem(get_dynamic_var('visitor_custom_parameters')) ||
                    (localStorage.getItem(get_dynamic_var('visitor_custom_parameters')) && localStorage.getItem(get_dynamic_var('visitor_custom_parameters')) != btoa(JSON.stringify(custom_parameters)))
                )
            ) {
                /* Generate the details for the visitor */
                let details = this.get_extra_details();

                /* Send the details */
                await send_data_fetch({
                    visitor_uuid: this.visitor_uuid,
                    type: 'initiate_visitor',
                    data: details
                });
            }

        } else {

            /* Generate it randomly */
            let visitor_uuid = get_random_id();

            this.visitor_uuid = visitor_uuid;

            /* Save it to localstorage */
            localStorage.setItem(get_dynamic_var('visitor_uuid'), this.visitor_uuid);

            /* Generate the details for the visitor */
            let details = this.get_extra_details();

            /* Send the details */
            await send_data_fetch({
                visitor_uuid,
                type: 'initiate_visitor',
                data: details
            });
        }

    }

    get_extra_details() {

        let data = {
            resolution: {
                width: window.screen.width,
                height: window.screen.height
            },

            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            theme: window?.matchMedia?.('(prefers-color-scheme:dark)')?.matches ? 'dark' : 'light'

            /* Extra detection based on the browser is made directly on the server side */
        };

        let custom_parameters = this.get_custom_parameters();

        if(custom_parameters) {
            data.custom_parameters = custom_parameters;

            /* Save it to localstorage */
            localStorage.setItem(get_dynamic_var('visitor_custom_parameters'), btoa(JSON.stringify(custom_parameters)));
        }

        return data;
    }

    get_custom_parameters() {
        /* Check for extra parameters */
        let this_script = document.querySelector(`script[src$="pixel/${pixel_key}"]`);

        if(this_script.dataset.customParameters) {

            try {
                let custom_parameters = JSON.parse(this_script.dataset.customParameters);

                return custom_parameters;

            } catch(error) {
                return false;
            }

        } else {

            return false;

        }
    }

}


/* Session class */
class AltumCodeEvents {

    /* Create and initiate the class with the proper parameters */
    async initiate() {

        this.visitor_uuid = localStorage.getItem(get_dynamic_var('visitor_uuid'));
        this.visitor_session_uuid = localStorage.getItem(get_dynamic_var('visitor_session_uuid'));

        /* Store UUID of the last event for page loads */
        localStorage.setItem(get_dynamic_var('visitor_session_event_uuid'), get_random_id());
        this.visitor_session_event_uuid = localStorage.getItem(get_dynamic_var('visitor_session_event_uuid'));

        /* Get the session date if existing to detect the current session */
        let visitor_session_date = localStorage.getItem(get_dynamic_var('visitor_session_date'));
        let date = new Date();

        /* Check if the current page is the first for this session */
        if(!visitor_session_date || (visitor_session_date && date - (new Date(visitor_session_date)) > 30*60*1000)) {

            /* Generate the session uuid */
            this.visitor_session_uuid = get_random_id();
            localStorage.setItem(get_dynamic_var('visitor_session_uuid'), this.visitor_session_uuid);

            /* Emit the landing page event */
            await this.event_landing_page();

        } else {

            /* Emit the pageview event */
            await this.event_pageview()

        }

        /* Set the new session date */
        localStorage.setItem(get_dynamic_var('visitor_session_date'), date.toJSON());

        /* Expose function to window */
        window[pixel_exposed_identifier] = {

            goal: async (key) => {
                await this.event_goal_conversion(key);
            }

        };

        /* Initiate event handlers */
        if(pixel_track_events_children) {
            this.initiate_event_handlers();
        }

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

        /* Heatmaps & RRWEB replays */
        /* Session replay buffer */
        let session_replay_events_buffer = [];
        let session_replay_send_timer = null;
        const session_replay_batch_delay = 1000; /* 1.5 seconds */
        const session_replay_batch_size = 100;

        /* Heatmap snapshot buffer, will be used if a heatmap for this page is detected */
        let heatmap_events = null;
        let heatmap_should_capture = false;
        let heatmap_id_active = null;

        /* Helpers to capture exactly one [meta, fullsnapshot] pair for heatmap */
        let heatmap_meta_event = null;
        let heatmap_snapshot_sent = false;

        /* Heatmap detection */
        if(pixel_heatmaps.length) {
            let device = get_device_type();
            let current_domain = get_current_url_domain_no_www();

            /* Iterate on all heatmaps and initiate them if needed */
            for(let heatmap of pixel_heatmaps) {

                /* Check if heatmap url matches the current url */
                if(heatmap.url == current_domain || heatmap.url == 'www.'+current_domain) {

                    /* If needed, snapshot the page and send the data */
                    if(!heatmap[`snapshot_id_${device}`]) {
                        heatmap_events = [];
                        heatmap_should_capture = true;
                        heatmap_id_active = heatmap.heatmap_id;

                        /* Reset helpers to ensure a single snapshot is sent */
                        heatmap_meta_event = null;
                        heatmap_snapshot_sent = false;
                    }

                    /* Initiate the events handlers for heatmaps */
                    this.initiate_event_handler_click(heatmap.heatmap_id);
                    // this.initiate_event_handler_scroll(heatmap.heatmap_id);

                    /* No need to continue the loop if found the heatmap */
                    break;
                }
            }
        }

        let replay_events_this_second = 0;
        const replay_max_events_per_second = 100;

        setInterval(() => { replay_events_this_second = 0; }, 1000);

        const safe_add_replay_event = event => {
            if(replay_events_this_second >= replay_max_events_per_second) {
                return;
            }
            replay_events_this_second++;
            add_session_replay_event_to_buffer(event);
        };

        /* Add session replay event to buffer and batch send */
        const add_session_replay_event_to_buffer = event => {
            session_replay_events_buffer.push(event);

            /* If buffer is full, send now */
            if(session_replay_events_buffer.length >= session_replay_batch_size) {
                flush_session_replay_buffer_now();
                return;
            }

            /* Start/reset timer */
            if(session_replay_send_timer) clearTimeout(session_replay_send_timer);
            session_replay_send_timer = setTimeout(flush_session_replay_buffer_now, session_replay_batch_delay);
        };

        /* Flush the session replay buffer now */
        const flush_session_replay_buffer_now = async (use_beacon = false) => {
            if(session_replay_events_buffer.length === 0) return;

            let replay_data = {
                visitor_uuid: this.visitor_uuid,
                visitor_session_uuid: this.visitor_session_uuid,
                visitor_session_event_uuid: this.visitor_session_event_uuid,
                type: 'replays',
                data: session_replay_events_buffer
            };


            if(use_beacon) {
                /* Use beacon for final flush on unload */
                send_data_beacon(replay_data);
            } else {
                /* Use fetch for normal batch sending */
                await send_data_fetch(replay_data);
            }

            session_replay_events_buffer = [];
            if(session_replay_send_timer) clearTimeout(session_replay_send_timer);
            session_replay_send_timer = null;
        };

        /* Use ONE rrwebRecord for both replay and heatmap */
        if(pixel_track_sessions_replays || heatmap_should_capture) {
            rrwebRecord({
                sampling: {
                    mouseInteraction: {
                        MouseUp: true,
                        MouseDown: true,
                        Click: true,
                        Focus: true,
                        Blur: true,
                        DblClick: false,
                        ContextMenu: false,
                        TouchStart: false,
                        TouchEnd: false
                    },
                    mousemove: 200,
                    scroll: 200,
                    media: 200,
                    mutation: 150
                },

                /* Convert all text inputs to *** for privacy reasons */
                maskAllInputs: true,

                /* Remove unnecessary parts of the page */
                slimDOMOptions: {
                    comment: true,
                    headFavicon: true,
                    headWhitespace: true,
                    headMetaDescKeywords: true,
                    headMetaSocial: true,
                    headMetaRobots: true,
                    headMetaHttpEquiv: true,
                    headMetaAuthorship: true,
                    headMetaVerification: true
                },
                emit: event => {
                    /* Handle heatmap FIRST and synchronously */
                    if(heatmap_should_capture && !heatmap_snapshot_sent) {
                        /* Remember the very first META event */
                        if(event.type === 4 && !heatmap_meta_event) {
                            heatmap_meta_event = event;
                        }

                        /* On the first FULL SNAPSHOT after META, send snapshot */
                        if(event.type === 2 && heatmap_meta_event) {
                            /* Send only the minimal [meta, fullsnapshot] pair */
                            send_data_fetch({
                                type: 'heatmap_snapshot',
                                heatmap_id: heatmap_id_active,
                                data: [heatmap_meta_event, event]
                            }).catch(() => { /* swallow to avoid breaking rrweb emit */ });

                            heatmap_snapshot_sent = true;
                            heatmap_should_capture = false; /* Only send once */
                            heatmap_events = null; /* free memory */
                        }
                    }

                    /* Always push to session replay if enabled */
                    if(pixel_track_sessions_replays) {
                        /* Block selection-change spam: incremental event with source = 14 */
                        if(event.type === 3 && event.data && event.data.source === 14) {
                            return;
                        }

                        safe_add_replay_event(event);

                        /* If the buffer holds the initial [meta, fullsnapshot], flush soon but don't block emit */
                        if(
                            session_replay_events_buffer.length >= 2 &&
                            session_replay_events_buffer[0].type === 4 &&
                            session_replay_events_buffer[1].type === 2
                        ) {
                            /* Defer flush so rrweb’s emit path is never awaited */
                            setTimeout(() => {
                                flush_session_replay_buffer_now().catch(() => {});
                            }, 0);
                        }
                    }
                }
            });
        }

        /* Always flush session replay buffer on pagehide and beforeunload */
        const termination_event = 'onpagehide' in self ? 'pagehide' : 'unload';
        window.addEventListener(termination_event, () => { flush_session_replay_buffer_now(true); }, {capture: true});
        window.addEventListener('beforeunload', () => { flush_session_replay_buffer_now(true); });
    }

    initiate_event_handlers() {
        this.initiate_event_handler_click();

        this.initiate_event_handler_scroll();

        this.initiate_event_handler_forms();

        this.initiate_event_handler_resize();
    }

    /* Mouse click event handler */
    initiate_event_handler_click(heatmap_id = null) {
        /* Last click data for comparison */
        let click_data = '';

        /* Count of the clicks when the click is simply the same */
        let clicks_count = 1;

        /* Timer for the click so we dont spam the server */
        let timer = false;

        document.addEventListener('click', event => {

            /* Make sure the event was fired by the actual user and not programatically */
            if(!event.isTrusted) {
                return false;
            }

            /* Timeout depending on the element that has been clicked so that we can detect actual url changes clicks */
            let timeout = event.target.tagName == 'A' && !event.target.getAttribute('href').startsWith('#') ? 0 : 500;

            /* Get the text of the area that the user clicked */
            let text = (typeof event.target.innerText === 'string' && event.target.innerText.length > 61)
                ? `${event.target.innerText.substr(0, 61)}...`
                : event.target.innerText || '';

            let data = {
                mouse: {
                    x: event.pageX,
                    y: event.pageY
                },

                text: text,
                element: event.target.tagName.toLowerCase()
            };


            /* Check if the click is the same with the previous */
            if(JSON.stringify(data) == click_data) {
                clicks_count++;

                clearInterval(timer);
            }

            click_data = JSON.stringify(data);

            timer = setTimeout(() => {

                this.event_child('click', data, clicks_count, heatmap_id);

                clicks_count = 1;

            }, timeout);

        });
    }

    /* Scroll event handler */
    initiate_event_handler_scroll(heatmap_id = null) {

        /* Timer for the scroll so we dont spam the server */
        let timer = false;

        document.addEventListener('scroll', event => {

            /* Make sure the event was fired by the actual user and not programatically */
            if(!event.isTrusted) {
                return false;
            }

            let data = {
                scroll: {
                    percentage: parseInt((document.documentElement['scrollTop'] || document.body['scrollTop']) / ((document.documentElement['scrollHeight'] || document.body['scrollHeight']) - document.documentElement.clientHeight) * 100)

                    /* Do not store the top value, store the percentage of scrolling instead */
                    /* top: window.pageYOffset || document.documentElement.scrollTop, */

                    /* Most websites do not have a horizontal scroll */
                    /* left: window.pageXOffset || document.documentElement.scrollLeft */
                }
            };


            clearInterval(timer);

            timer = setTimeout(() => {

                this.event_child('scroll', data, 1, heatmap_id);

            }, 500);

        }, { passive: true });
    }

    /* Inputs event handler */
    initiate_event_handler_forms() {

        let event_handler_form = event => {

            /* Store data from the form */
            let data = {
                form: {

                }
            };

            /* INPUT VALUES ARE NOT STORED ANYMORE FOR PRIVACY REASONS */
            /* let form_element = event.target; */
            /*
            form_element.querySelectorAll('input').forEach(input_element => {
                if(input_element.type == 'password' || input_element.type == 'hidden') {
                    return;
                }
                if(input_element.name.indexOf('captcha') !== -1) {
                    return;
                }
                data.form[input_element.name] = input_element.value;
            });
            */

            /* Submit the event */
            this.event_child('form', data);

        };

        /* Make sure to know all of the form submissions on the page */
        document.querySelectorAll('form').forEach(form_element => {

            form_element.addEventListener('submit', event_handler_form);

        });

    }

    /* Window resize event handler */
    initiate_event_handler_resize() {

        /* Timer for the scroll so we dont spam the server */
        let timer = false;

        window.addEventListener('resize', event => {

            /* Make sure the event was fired by the actual user and not programatically */
            if(!event.isTrusted) {
                return false;
            }

            let data = {
                viewport: this.get_viewport()
            };


            clearInterval(timer);

            timer = setTimeout(() => {

                this.event_child('resize', data);

            }, 500);

        });

    }

    async event_landing_page() {
        let query_parameters = new URL(document.location.toString()).searchParams.toString();

        let data = {
            path: window.location.pathname + (pixel_query_parameters_tracking_is_enabled && query_parameters ? '?' + query_parameters : ''),
            title: document.title,
            referrer: document.referrer.includes(`${location.protocol}//${location.host}${location.pathname}`) ? null : document.referrer,

            utm: this.get_utm_params(),

            viewport: this.get_viewport()
        };

        await this.send_data({
            visitor_uuid: this.visitor_uuid,
            visitor_session_uuid: this.visitor_session_uuid,
            visitor_session_event_uuid: this.visitor_session_event_uuid,
            type: 'landing_page',
            data
        });

    }

    async event_pageview() {

        let data = {
            path: window.location.pathname + (pixel_query_parameters_tracking_is_enabled ? '?' + new URL(document.location.toString()).searchParams.toString() : ''),
            title: document.title,
            referrer: document.referrer.includes(`${location.protocol}//${location.host}${location.pathname}`) ? null : document.referrer,

            utm: this.get_utm_params(),

            viewport: this.get_viewport()
        };

        await this.send_data({
            visitor_uuid: this.visitor_uuid,
            visitor_session_uuid: this.visitor_session_uuid,
            visitor_session_event_uuid: this.visitor_session_event_uuid,
            type: 'pageview',
            data
        });

    }

    event_child(type, data = {}, count = 1, heatmap_id = null) {

        send_data_beacon({
            visitor_uuid: this.visitor_uuid,
            visitor_session_uuid: this.visitor_session_uuid,
            visitor_session_event_uuid: this.visitor_session_event_uuid,
            type,
            data,
            count,
            heatmap_id
        });

    }

    async event_goal_conversion(key) {

        /* Iterate on all goals and initiate them if needed */
        for(let goal of pixel_goals) {

            /* Check if goal url matches the current url */
            if(goal.key == key && !localStorage.getItem(get_dynamic_var(`visitor_goal_${goal.key}`))) {

                /* Send the goal completion */
                await send_data_fetch({
                    visitor_uuid: this.visitor_uuid,
                    visitor_session_uuid: this.visitor_session_uuid,
                    visitor_session_event_uuid: this.visitor_session_event_uuid,
                    type: 'goal_conversion',
                    goal_key: goal.key
                });

                /* Set it in the local storage to make sure to not send it again */
                localStorage.setItem(get_dynamic_var(`visitor_goal_${goal.key}`), true);

                break;
            }

        }
    }

    async send_data(data) {

        let response = await send_data_fetch(data);

        /* Handle potential needs to refresh the visitor or session */
        if(response && response.details && response.details.refresh) {

            switch(response.details.refresh) {
                case 'visitor':

                    localStorage.removeItem(get_dynamic_var('visitor_uuid'));

                    /* reInitiate the Visitor */
                    let altumcode_visitor = new AltumCodeVisitor();

                    await altumcode_visitor.initiate();

                    break;

                case 'session':

                    localStorage.removeItem(get_dynamic_var('visitor_session_uuid'));
                    localStorage.removeItem(get_dynamic_var('visitor_session_date'));

                    /* reInitiate the Events */
                    let altumcode_events = new AltumCodeEvents();

                    await altumcode_events.initiate();

                    break;
            }

        }

    }

    get_viewport() {
        return {
            width: window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth,
            height: window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight,
        }
    }

    get_utm_params() {
        let url_params = new URLSearchParams(window.location.search);

        return {
            source: url_params.get('utm_source'),
            medium: url_params.get('utm_medium'),
            campaign: url_params.get('utm_campaign'),
        };
    }

}
