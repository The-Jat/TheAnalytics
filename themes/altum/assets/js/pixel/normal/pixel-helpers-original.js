/* Helpers */
let get_dynamic_var = string => {
    return `__${pixel_key}_${string}`;
};

let get_random_id = () => {
    return crypto.randomUUID().replace(/-/g, "");
};

let is_do_not_track = () => {
    if(window.doNotTrack || navigator.doNotTrack || navigator.msDoNotTrack) {

        return window.doNotTrack == "1" || navigator.doNotTrack == "yes" || navigator.doNotTrack == "1" || navigator.msDoNotTrack == "1";

    } else {
        return false;
    }
};

let is_optout = () => {
    let params = (new URL(document.location)).searchParams;

    /* Disable tracking and set a cookie for the future */
    let pixel_optout = params.get('pixel_optout');

    if(pixel_optout !== null) {
        pixel_optout = pixel_optout == 'true';

        if(pixel_optout) {
            localStorage.setItem(get_dynamic_var('pixel_optout'), 'true');

            return true;
        } else {
            localStorage.setItem(get_dynamic_var('pixel_optout'), 'false');

            return false;
        }

    }

    return localStorage.getItem(get_dynamic_var('pixel_optout')) == 'true';
};

let get_device_type = () => {

    let android = /(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/gi;

    let tablet = /(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/gi;

    return android.test(navigator.userAgent) ? 'mobile' : tablet.test(navigator.userAgent) ? 'tablet' : 'desktop';
};

let get_current_url_domain_no_www = () => {
    let url = window.location.href.replace(window.location.protocol + '//', '');

    /* Remove www. from the host */
    if(url.startsWith('www.')) {
        url = url.replace('www.', '');
    }

    return url;
}
