/**
 * JavaScript pour l'interface d'administration WC Multi-Step Booking Checkout
 */

(function ($) {
  "use strict";

  // Objet principal admin
  const WCMultiStepBookingCheckoutAdmin = {
    // Initialisation
    init: function () {
      this.bindEvents();
      this.validateConfiguration();
      console.log("WC Multi-Step Booking Checkout Admin initialisé");
    },

    // Liaison des événements
    bindEvents: function () {
      // Validation en temps réel des pages
      $(".wc-msbc-pages-table select").on(
        "change",
        this.validatePageSelection.bind(this)
      );

      // Validation TTL session
      $('input[name="wc_msbc_settings[session_ttl]"]').on(
        "input",
        this.validateSessionTTL.bind(this)
      );

      // Sauvegarde via AJAX (optionnel)
      $(".wc-msbc-settings-form").on(
        "submit",
        this.handleFormSubmit.bind(this)
      );

      // Copie des shortcodes au clic
      $(".wc-msbc-shortcode-item code").on(
        "click",
        this.copyShortcode.bind(this)
      );
    },

    // Validation sélection de page
    validatePageSelection: function (e) {
      const $select = $(e.currentTarget);
      const pageId = parseInt($select.val(), 10);
      const $row = $select.closest("tr");

      // Suppression des messages précédents
      $row.find(".wc-msbc-page-message").remove();

      if (pageId > 0) {
        // Vérification que la page n'est pas utilisée ailleurs
        const $otherSelects = $(".wc-msbc-pages-table select").not($select);
        let isDuplicate = false;

        $otherSelects.each(function () {
          if (parseInt($(this).val(), 10) === pageId) {
            isDuplicate = true;
            return false;
          }
        });

        if (isDuplicate) {
          this.showPageMessage(
            $row,
            "Cette page est déjà utilisée pour une autre étape.",
            "warning"
          );
        } else {
          this.showPageMessage(
            $row,
            "Page sélectionnée avec succès.",
            "success"
          );
        }
      }

      // Mise à jour du statut global
      this.updateConfigurationStatus();
    },

    // Validation TTL session
    validateSessionTTL: function (e) {
      const $input = $(e.currentTarget);
      const value = parseInt($input.val(), 10);
      const $container = $input.closest("td") || $input.closest(".form-field");

      // Suppression des messages précédents
      $container.find(".wc-msbc-ttl-message").remove();

      if (isNaN(value) || value < 5 || value > 60) {
        $input.addClass("error");
        $container.append(
          '<p class="wc-msbc-ttl-message error">La durée doit être entre 5 et 60 minutes.</p>'
        );
      } else {
        $input.removeClass("error");
        $container.append(
          '<p class="wc-msbc-ttl-message success">Durée valide.</p>'
        );
      }
    },

    // Affichage message page
    showPageMessage: function ($row, message, type) {
      const $message = $(
        '<p class="wc-msbc-page-message ' + type + '">' + message + "</p>"
      );
      $row.find("td:last-child").append($message);

      // Auto-suppression pour les messages de succès
      if (type === "success") {
        setTimeout(function () {
          $message.fadeOut(300, function () {
            $(this).remove();
          });
        }, 2000);
      }
    },

    // Mise à jour statut configuration
    updateConfigurationStatus: function () {
      let configuredPages = 0;
      const totalPages = 4;

      $(".wc-msbc-pages-table select").each(function () {
        if (parseInt($(this).val(), 10) > 0) {
          configuredPages++;
        }
      });

      const $statusValue = $(".wc-msbc-status-item")
        .first()
        .find(".wc-msbc-status-value");
      $statusValue.text(configuredPages + "/" + totalPages);

      // Mise à jour classe CSS
      $statusValue.removeClass("success warning error");
      if (configuredPages === totalPages) {
        $statusValue.addClass("success");
      } else if (configuredPages > 0) {
        $statusValue.addClass("warning");
      } else {
        $statusValue.addClass("error");
      }
    },

    // Validation configuration globale
    validateConfiguration: function () {
      this.updateConfigurationStatus();

      // Vérification doublons
      this.checkDuplicatePages();
    },

    // Vérification pages dupliquées
    checkDuplicatePages: function () {
      const pageIds = [];
      const $selects = $(".wc-msbc-pages-table select");

      $selects.each(function () {
        const pageId = parseInt($(this).val(), 10);
        if (pageId > 0) {
          if (pageIds.includes(pageId)) {
            $(this).addClass("error");
          } else {
            $(this).removeClass("error");
            pageIds.push(pageId);
          }
        }
      });
    },

    // Copie shortcode
    copyShortcode: function (e) {
      const $code = $(e.currentTarget);
      const shortcode = $code.text();

      // Copie dans le presse-papiers
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(shortcode)
          .then(function () {
            WCMultiStepBookingCheckoutAdmin.showCopyFeedback(
              $code,
              "Shortcode copié !"
            );
          })
          .catch(function () {
            WCMultiStepBookingCheckoutAdmin.fallbackCopyShortcode(
              $code,
              shortcode
            );
          });
      } else {
        this.fallbackCopyShortcode($code, shortcode);
      }
    },

    // Copie fallback
    fallbackCopyShortcode: function ($code, shortcode) {
      // Création d'un élément temporaire
      const $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(shortcode).select();

      try {
        document.execCommand("copy");
        this.showCopyFeedback($code, "Shortcode copié !");
      } catch (err) {
        this.showCopyFeedback($code, "Impossible de copier automatiquement");
      }

      $temp.remove();
    },

    // Feedback copie
    showCopyFeedback: function ($element, message) {
      const $feedback = $(
        '<span class="wc-msbc-copy-feedback">' + message + "</span>"
      );
      $element.after($feedback);

      setTimeout(function () {
        $feedback.fadeOut(300, function () {
          $(this).remove();
        });
      }, 1500);
    },

    // Gestion soumission formulaire
    handleFormSubmit: function (e) {
      // Validation finale avant soumission
      if (!this.finalValidation()) {
        e.preventDefault();
        this.showGlobalMessage(
          "Veuillez corriger les erreurs avant de sauvegarder.",
          "error"
        );
        return false;
      }

      // Affichage message de sauvegarde
      this.showGlobalMessage("Sauvegarde en cours...", "info");
    },

    // Validation finale
    finalValidation: function () {
      let isValid = true;

      // Vérification TTL
      const ttl = parseInt(
        $('input[name="wc_msbc_settings[session_ttl]"]').val(),
        10
      );
      if (isNaN(ttl) || ttl < 5 || ttl > 60) {
        isValid = false;
      }

      // Vérification doublons pages
      const pageIds = [];
      $(".wc-msbc-pages-table select").each(function () {
        const pageId = parseInt($(this).val(), 10);
        if (pageId > 0) {
          if (pageIds.includes(pageId)) {
            isValid = false;
          } else {
            pageIds.push(pageId);
          }
        }
      });

      return isValid;
    },

    // Message global
    showGlobalMessage: function (message, type) {
      // Suppression anciens messages
      $(".wc-msbc-global-message").remove();

      const $message = $(
        '<div class="wc-msbc-global-message notice notice-' +
          type +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );
      $(".wrap h1").after($message);

      // Auto-suppression
      if (type === "success" || type === "info") {
        setTimeout(function () {
          $message.fadeOut(300, function () {
            $(this).remove();
          });
        }, 3000);
      }
    },
  };

  // Initialisation au chargement DOM
  $(document).ready(function () {
    WCMultiStepBookingCheckoutAdmin.init();
  });

  // Exposition globale pour debug
  window.WCMultiStepBookingCheckoutAdmin = WCMultiStepBookingCheckoutAdmin;
})(jQuery);
