'use strict';

/**
 * Behavioral smoke tests for SOAP-provided text in HTML rendering.
 * Run: node tests/js-render-safety-smoke.js
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const htmlBySelector = {};
const textBySelector = {};
function jquery(selector) {
  return {
    length: 1,
    each() { return this; },
    val() { return ''; },
    html(value) {
      if (arguments.length > 0) htmlBySelector[selector] = value;
      return htmlBySelector[selector] || '';
    },
    text(value) {
      if (arguments.length > 0) textBySelector[selector] = value;
      return textBySelector[selector] || '';
    }
  };
}

const windowMock = {
  jQuery: jquery,
  PontifexOI: {
    normalizeExam(value) { return value; },
    getSelectedFilter(_name, fallback) { return fallback; }
  }
};

const source = fs.readFileSync(path.join(__dirname, '../assets/js/table.js'), 'utf8');
vm.runInNewContext(source, { window: windowMock, jQuery: jquery }, {
  filename: 'assets/js/table.js',
  timeout: 1000
});

const api = windowMock.PontifexOI;
assert.equal(api.escapeHtml('&<>"\''), '&amp;&lt;&gt;&quot;&#039;');
assert.equal(api.escapeAttr('"><img>'), '&quot;&gt;&lt;img&gt;');

const maliciousRow = {
  date: '<img src=x onerror=alert(1)>',
  time: '"><script>alert(1)</script>',
  location: '</td><svg onload=alert(1)>',
  province: '"><iframe>',
  exam: '"><script>evil</script>',
  language: '" autofocus',
  material: '" onclick="evil()',
  spots: '2'
};

api.renderTableRows([maliciousRow], 1, 1, '/cursus-inschrijven/');
const tableHtml = htmlBySelector['.pontifex-oi-table tbody, .pontifex-oi-table-custom tbody'];
assert.ok(tableHtml, 'Table renderer should emit markup.');
assert.ok(tableHtml.includes('&lt;img'), 'Date should be escaped as visible text.');
assert.ok(tableHtml.includes('&lt;svg'), 'Location should be escaped as visible text.');
assert.ok(tableHtml.includes('&quot;'), 'Dynamic attributes should escape quotes.');
assert.ok(!tableHtml.includes('<img'), 'Unsafe image elements must not be generated.');
assert.ok(!tableHtml.includes('<script>'), 'Unsafe script elements must not be generated.');
assert.ok(!tableHtml.includes('<svg'), 'Unsafe SVG elements must not be generated.');
assert.ok(!tableHtml.includes('onclick="evil()'), 'An untrusted attribute must not be injected.');

api.renderCards([maliciousRow], '/cursus-inschrijven/');
const cardHtml = htmlBySelector['#pontifex-oi-cards-container'];
assert.ok(cardHtml, 'Card renderer should emit markup.');
assert.ok(cardHtml.includes('&lt;img'), 'Card date should be escaped.');
assert.ok(!cardHtml.includes('<script>'), 'Cards must not contain injected script elements.');
assert.ok(!cardHtml.includes('<svg'), 'Cards must not contain injected SVG elements.');
assert.ok(!cardHtml.includes('onclick="evil()'), 'Cards must not contain injected attributes.');

console.log('Pontifex planning table and card HTML escaping: OK');
