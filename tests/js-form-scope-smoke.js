'use strict';

/**
 * Runtime simulation: submit the second of two registration forms.
 * Global DOM selectors must not mix candidates or payment amounts.
 * Does not contact WordPress, Mollie, SOAP or SMTP.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const submitted = { data: null, url: null };
const events = {};
const documentMock = {};
const forms = [
  { type: 'form', index: 0, amount: '111.00', candidate: 'Eerste' },
  { type: 'form', index: 1, amount: '222.00', candidate: 'Tweede' }
];

function collection(values) {
  return {
    each(fn) { values.forEach((value, i) => fn.call(value, i, value)); return this; },
    removeClass() { return this; },
    prop() { return this; }
  };
}

function field(name, value) {
  return {
    attr(key) { return key === 'name' ? name : key === 'type' ? 'text' : undefined; },
    val() { return value; },
    data() { return undefined; },
    is() { return false; }
  };
}

function candidateRow(form) {
  return {
    type: 'row',
    find(selector) {
      if (selector.includes('candidate_fullname')) return field('candidate_fullname[]', form.candidate);
      if (selector.includes('candidate_infix')) return field('candidate_infix[]', '');
      if (selector.includes('candidate_lastname')) return field('candidate_lastname[]', 'Achternaam');
      if (selector.includes('candidate_birthdate')) return field('candidate_birthdate[]', '01-01-1995');
      throw new Error('Unexpected candidate field: ' + selector);
    }
  };
}

const rows = forms.map(candidateRow);

function wrapForm(form) {
  return {
    find(selector) {
      if (selector === '[required]') return collection([]);
      if (selector === '.invalid-field, .error') return collection([]);
      if (selector === '.pontifex-oi-candidate-row') return collection([rows[form.index]]);
      if (selector.includes('input:not([name*="candidate_"]')) {
        return collection([
          field('exam_type', 'los-examen-vca-basis'),
          field('order_email', 'test@example.invalid')
        ]);
      }
      if (selector === 'input[name="payment_amount"]') return field('payment_amount', form.amount);
      if (selector.includes('button[type=submit]')) return collection([]);
      throw new Error('Unexpected form selector: ' + selector);
    }
  };
}

function jquery(selector) {
  if (typeof selector === 'function') { selector(jquery); return; }
  if (selector === documentMock) {
    return { on(type, target, fn) { events[type] = fn; return this; } };
  }
  if (selector && selector.type === 'row') return selector;
  if (selector && selector.type === 'form') return wrapForm(selector);
  if (selector && typeof selector.attr === 'function' && typeof selector.val === 'function') {
    return selector;
  }
  // Intentionally wrong global fixtures: old code would pick these.
  if (selector === '.pontifex-oi-candidate-row') return collection(rows);
  if (selector === '#payment_amount') return field('payment_amount', forms[0].amount);
  throw new Error('Unexpected global selector: ' + selector);
}

jquery.post = (url, data, callback) => {
  submitted.url = url;
  submitted.data = data;
  callback({ success: false, data: { message: 'fixture' } });
  return { fail() { return this; } };
};

const context = {
  jQuery: jquery,
  document: documentMock,
  window: { pontifexOiVars: { ajaxurl: '/wp-admin/admin-ajax.php', nonce: 'fake-nonce' } },
  alert() {},
  setTimeout() {}
};

const source = fs.readFileSync(path.join(__dirname, '../assets/js/form-validation.js'), 'utf8');
vm.runInNewContext(source, context, { filename: 'assets/js/form-validation.js', timeout: 1000 });
assert.equal(typeof events.submit, 'function', 'A submit handler must be attached.');
events.submit.call(forms[1], { preventDefault() {} });

assert.ok(submitted.data, 'AJAX payload must be created.');
assert.equal(submitted.data.order.candidate_fullname.length, 1, 'Only current form candidates allowed.');
assert.equal(submitted.data.order.candidate_fullname[0], 'Tweede');
assert.equal(submitted.data.amount, '222.00', 'Payment amount belongs to the submitted form.');
console.log('Pontifex registration form scoping: OK');
