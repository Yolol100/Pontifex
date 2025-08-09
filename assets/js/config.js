// config.js
(function(window) {
  'use strict';

  window.PontifexOIConfig = {
    ajaxUrl: (typeof PontifexOiAjax !== 'undefined') ? PontifexOiAjax.ajax_url : '',
    registrationPageUrl: '/cursus-inschrijven/',
    planningPageUrl: '/cursus-zoeken/',
    examWeekend: ['vca-basis-weekend', 'vca-vol-weekend'],

    EXAM_PRODUCTS: {
      'los-examen-vca-basis': { label: 'VCA Basis', prices: { 'nl': 129, 'en': 129 } },
      'los-examen-vca-vol': { label: 'VCA Vol', prices: { 'nl': 139, 'en': 139 } },
      'vca-basis-weekend': { label: 'VCA Basis Cursus Weekend', prices: { 'nl': 245 } },
      'vca-vol-weekend': { label: 'VCA Vol Cursus Weekend', prices: { 'nl': 245 } },
    },

    MATERIAL_PRODUCTS: {
      'e-learning-vca-basis-nl': { label: 'E-learning VCA Basis (NL)', price: 29 },
      'vca-basis-proefexamens-nl': { label: 'VCA Basis Proefexamens (NL)', price: 25 },
      'boek-vca-basis-nl': { label: 'Boek VCA Basis (NL)', price: 36 },
      'boek-vca-combi-nl': { label: 'Boek VCA Combi (NL)', price: 49 },
      'e-learning-vca-vol-nl': { label: 'E-learning VCA Vol (NL)', price: 39 },
      'vca-vol-proefexamens-nl': { label: 'VCA Vol Proefexamens (NL)', price: 25 },
      'boek-vca-vol-nl': { label: 'Boek VCA Vol (NL)', price: 42 },
      'boek-vca-combi-vol-nl': { label: 'Boek VCA Combi (NL)', price: 49 },
      'e-learning-vca-basis-en': { label: 'E-learning VCA Basis (EN)', price: 49 },
      'boek-vca-basis-en': { label: 'Boek VCA Basis (EN)', price: 56 },
      'boek-vca-combi-en': { label: 'Boek VCA Combi (EN)', price: 69 },
      'e-learning-vca-vol-en': { label: 'E-learning VCA Vol (EN)', price: 59 },
      'boek-vca-vol-en': { label: 'Boek VCA Vol (EN)', price: 62 },
      'boek-vca-combi-vol-en': { label: 'Boek VCA Combi (EN)', price: 69 },
    },

    MATERIAL_COMBIS: {
      '1': [],
      '2_basis': ['boek-vca-basis-nl'],
      '2_vol': ['boek-vca-vol-nl'],
      '4_basis': ['e-learning-vca-basis-nl'],
      '4_vol': ['e-learning-vca-vol-nl'],
      '5_basis': ['vca-basis-proefexamens-nl'],
      '5_vol': ['vca-vol-proefexamens-nl'],
      '6_basis': ['boek-vca-basis-nl', 'vca-basis-proefexamens-nl'],
      '6_vol': ['boek-vca-vol-nl', 'vca-vol-proefexamens-nl'],
      '7_basis': ['e-learning-vca-basis-nl', 'vca-basis-proefexamens-nl'],
      '7_vol': ['e-learning-vca-vol-nl', 'vca-vol-proefexamens-nl'],
    },

    extraMaterialCheckboxes: {
      'los-examen-vca-basis_nl': [
        { id: 'e-learning-vca-basis-nl', label: 'E-learning VCA Basis (NL)', price: 29 },
        { id: 'vca-basis-proefexamens-nl', label: 'VCA Basis Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-basis-nl', label: 'Boek VCA Basis (NL)', price: 36 },
        { id: 'boek-vca-combi-nl', label: 'Boek VCA Combi (NL)', price: 49 },
      ],
      'los-examen-vca-vol_nl': [
        { id: 'e-learning-vca-vol-nl', label: 'E-learning VCA Vol (NL)', price: 39 },
        { id: 'vca-vol-proefexamens-nl', label: 'VCA Vol Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-vol-nl', label: 'Boek VCA Vol (NL)', price: 42 },
        { id: 'boek-vca-combi-vol-nl', label: 'Boek VCA Combi (NL)', price: 49 },
      ],
      'los-examen-vca-basis_en': [
        { id: 'e-learning-vca-basis-en', label: 'E-learning VCA Basis (EN)', price: 49 },
        { id: 'boek-vca-basis-en', label: 'Boek VCA Basis (EN)', price: 56 },
        { id: 'boek-vca-combi-en', label: 'Boek VCA Combi (EN)', price: 69 },
      ],
      'los-examen-vca-vol_en': [
        { id: 'e-learning-vca-vol-en', label: 'E-learning VCA Vol (EN)', price: 59 },
        { id: 'boek-vca-vol-en', label: 'Boek VCA Vol (EN)', price: 62 },
        { id: 'boek-vca-combi-vol-en', label: 'Boek VCA Combi (EN)', price: 69 },
      ],
    },
  };

})(window);