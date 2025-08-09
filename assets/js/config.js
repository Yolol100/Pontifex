// config.js
(function(window) {
  'use strict';

  window.PontifexOIConfig = {
    ajaxUrl: (typeof PontifexOiAjax !== 'undefined') ? PontifexOiAjax.ajax_url : '',
    registrationPageUrl: '/cursus-inschrijven/',
    planningPageUrl: '/cursus-zoeken/',

    weekendAllowedByExam: {
      'los-examen-vca-basis':       ['nl','en'],
      'los-examen-vca-basis-groen': ['nl'],
      'los-examen-vca-vol':         ['nl','en'],
      'los-examen-vca-vil':         ['nl','en']
    },

    availableLanguages: ['nl','en','de','fr','ar','bg','lt','pl','pt','ro','ru','tr','el','hu','it','hr','uk','sk','es','vi'],
    availableLanguageLabels: {
      nl:'Nederlands', en:'Engels', de:'Duits', fr:'Frans',
      ar:'Arabisch', bg:'Bulgaars', lt:'Litouws', pl:'Pools',
      pt:'Portugees', ro:'Roemeens', ru:'Russisch', tr:'Turks',
      el:'Grieks', hu:'Hongaars', it:'Italiaans', hr:'Kroatisch',
      uk:'Oekraïens', sk:'Slowaaks', es:'Spaans', vi:'Vietnamees'
    },

    EXAM_PRODUCTS: {
      'los-examen-vca-basis': {
        label: 'VCA Basis',
        prices: {
          'nl': 129, 'de': 129, 'en': 129, 'fr': 129,
          'ar': 149, 'bg': 149, 'lt': 149, 'pl': 149,
          'pt': 149, 'ro': 149, 'ru': 149, 'tr': 149,
          'el': 184, 'hu': 184, 'it': 184, 'hr': 184,
          'uk': 184, 'sk': 184, 'es': 184, 'vi': 184
        }
      },
      'los-examen-vca-basis-groen': {
        label: 'VCA Basis Groen',
        prices: { 'nl': 129 }
      },
      'los-examen-vca-vol': {
        label: 'VCA Vol',
        prices: { 'nl': 129, 'de': 139, 'en': 139, 'fr': 139 }
      },
      'los-examen-vca-vil': {
        label: 'VCA VIL',
        prices: { 'nl': 129, 'en': 139 }
      }
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

      'cursus-weekend': { label: 'Cursus weekend', price: 245 }
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
      '7_vol': ['e-learning-vca-vol-nl', 'vca-vol-proefexamens-nl']
    },

    extraMaterialCheckboxes: {
      'los-examen-vca-basis_nl': [
        { id: 'e-learning-vca-basis-nl', label: 'E-learning VCA Basis (NL)', price: 29 },
        { id: 'vca-basis-proefexamens-nl', label: 'VCA Basis Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-basis-nl', label: 'Boek VCA Basis (NL)', price: 36 },
        { id: 'boek-vca-combi-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],
      'los-examen-vca-basis_en': [
        { id: 'e-learning-vca-basis-en', label: 'E-learning VCA Basis (EN)', price: 49 },
        { id: 'boek-vca-basis-en', label: 'Boek VCA Basis (EN)', price: 56 },
        { id: 'boek-vca-combi-en', label: 'Boek VCA Combi (EN)', price: 69 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],
      'los-examen-vca-basis_de': [],
      'los-examen-vca-basis_fr': [],

      'los-examen-vca-basis-groen_nl': [
        { id: 'e-learning-vca-basis-nl', label: 'E-learning VCA Basis (NL)', price: 29 },
        { id: 'vca-basis-proefexamens-nl', label: 'VCA Basis Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-basis-nl', label: 'Boek VCA Basis (NL)', price: 36 },
        { id: 'boek-vca-combi-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],

      'los-examen-vca-vol_nl': [
        { id: 'e-learning-vca-vol-nl', label: 'E-learning VCA Vol (NL)', price: 39 },
        { id: 'vca-vol-proefexamens-nl', label: 'VCA Vol Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-vol-nl', label: 'Boek VCA Vol (NL)', price: 42 },
        { id: 'boek-vca-combi-vol-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],
      'los-examen-vca-vol_en': [
        { id: 'e-learning-vca-vol-en', label: 'E-learning VCA Vol (EN)', price: 59 },
        { id: 'boek-vca-vol-en', label: 'Boek VCA Vol (EN)', price: 62 },
        { id: 'boek-vca-combi-vol-en', label: 'Boek VCA Combi (EN)', price: 69 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],
      'los-examen-vca-vol_de': [],
      'los-examen-vca-vol_fr': [],

      'los-examen-vca-vil_nl': [
        { id: 'e-learning-vca-vol-nl', label: 'E-learning VCA Vol (NL)', price: 39 },
        { id: 'vca-vol-proefexamens-nl', label: 'VCA Vol Proefexamens (NL)', price: 25 },
        { id: 'boek-vca-vol-nl', label: 'Boek VCA Vol (NL)', price: 42 },
        { id: 'boek-vca-combi-vol-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ],
      'los-examen-vca-vil_en': [
        { id: 'e-learning-vca-vol-en', label: 'E-learning VCA Vol (EN)', price: 59 },
        { id: 'boek-vca-vol-en', label: 'Boek VCA Vol (EN)', price: 62 },
        { id: 'boek-vca-combi-vol-en', label: 'Boek VCA Combi (EN)', price: 69 },
        { id: 'cursus-weekend', label: 'Cursus weekend', price: 245 }
      ]
    }
  };

})(window);