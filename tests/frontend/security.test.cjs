const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {safeUiObject} = require('../../resources/js/security/safeUiObject.cjs');
test('nested props and arrays cannot carry prototype pollution payloads', () => {
    const payload = JSON.parse('{"light":{"__proto__":{"polluted":true},"constructor":{"prototype":{"polluted":true}},"primary":"#ffffff"},"items":[{"prototype":{"bad":true},"text":"safe"}]}');
    const result = safeUiObject(payload);
    assert.deepEqual(result, {light:{primary:'#ffffff'},items:[{text:'safe'}]});
    assert.equal({}.polluted, undefined);
});
test('production app uses precompiled root and no date picker formatting sink', () => {
    assert.match(fs.readFileSync('webpack.config.js','utf8'), /vue\.runtime\.esm\.js/);
    assert.match(fs.readFileSync('resources/js/app.js','utf8'), /render: h => h\(Layout/);
    assert.doesNotMatch(fs.readFileSync('resources/js/components/Home/Calendar.vue','utf8'), /<v-date-picker|title-date-format/);
    assert.match(fs.readFileSync('resources/js/components/UserManual/UserManual.vue','utf8'), /DOMPurify\.sanitize/);
});
const vm = require('node:vm');
test('real HTTP interceptor announces offline/session expiry and strips unsafe JSON props', () => {
    const events = [];
    let success, failure;
    const axios = {defaults:{headers:{common:{}}},interceptors:{response:{use:(ok,bad)=>{success=ok;failure=bad;}}}};
    const context = {Promise,window:{dispatchEvent:e=>events.push(e.type)},document:{querySelector:()=>({content:'qa'})},CustomEvent:class {constructor(type){this.type=type;}},require:name=>name==='axios'?axios:name.includes('safeUiObject')?{safeUiObject}: {}};
    vm.runInNewContext(fs.readFileSync('resources/js/bootstrap.js','utf8'),context);
    assert.equal(axios.defaults.headers.common['X-Learning-Target'],'qa');
    failure({response:{status:419}}).catch(()=>{});
    failure({message:'Network Error'}).catch(()=>{});
    const response=success({data:JSON.parse('{"__proto__":{"polluted":true},"ok":1}')});
    assert.equal(response.data.ok,1); assert.equal(Object.hasOwn(response.data,'__proto__'),false);
    assert.deepEqual(events,['holatales-session-expired','holatales-network-unavailable','holatales-network-restored']);
});
test('interface translation chooses UI locale independently of target language', () => {
    const {translate} = require('../../resources/js/i18n.cjs');
    assert.equal(translate('library','en'),'Library');
    assert.equal(translate('library','vi'),'Thư viện');
});
