/**
 * api url
 * @type {string}
 */
const BASE_URL = 'http://' + window.location.host

/**
 * init http library
 * @type http object
 */
let Request = Vue.http;

/**
 * session manager
 * @param key
 * @param data
 * @returns {string}
 */
function session(key = '', data = '') {
    let act = key.split(".")[0];
    let par = key.split(".")[1];
    if (act === "post") {
        window.sessionStorage.setItem(par, data);
        return "true";
    }
    return window.sessionStorage.getItem(par);
}

/**
 * get url param
 * @returns value
 */
function get_url_param(key = '') {
    let param = {};
    let url = window.location.href;
    let arr = url.substr(url.indexOf('?') + 1).split('&');
    arr.forEach(item => {
        let tmp = item.split('=');
        param[tmp[0]] = tmp[1];
    });
    if (key) return param[key];
    return param
}

/**
 * link to some page
 * @param page
 * @param param
 */
function redirect(page = '', param = {}) {
    let p = [];
    Object.keys(param).forEach(key => {
        p.push(key + "=" + param[key]);
    })
    window.location.href = p.length === 0 ? './' + page + '.html' : './' + page + '.html?' + p.join("&");
}

/**
 * retun a random number
 * @param lower
 * @param upper
 * @returns {*}
 */
function random(lower, upper) {
    return Math.floor(Math.random() * (upper - lower + 1)) + lower;
}