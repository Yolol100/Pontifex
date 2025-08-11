// config.js (cleaned): consume PHP-localized data to avoid duplication.
(function(window){
  'use strict';
  // Prefer server-provided config (localized via PHP)
  if (typeof window.PontifexOIConfigData === 'undefined') {
    window.PontifexOIConfigData = window.PontifexOIConfig || {};
  }
  // Back-compat: mirror into PontifexOIConfig for older modules
  window.PontifexOIConfig = window.PontifexOIConfigData;
})(window);