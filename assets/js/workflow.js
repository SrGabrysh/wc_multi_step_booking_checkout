/**
 * JavaScript pour WC Multi-Step Booking Checkout
 * Gestion des interactions utilisateur et AJAX
 */

(function ($) {
  "use strict";

  // Objet principal du workflow
  const WCMultiStepBookingCheckout = {
    // Initialisation
    init: function () {
      this.bindEvents();
      this.updateProgressBar();
      console.log("WC Multi-Step Booking Checkout initialisé");
    },

    // Liaison des événements
    bindEvents: function () {
      // Bouton suivant
      $(document).on(
        "click",
        ".wc-msbc-next-btn",
        this.handleNextStep.bind(this)
      );

      // Bouton précédent
      $(document).on(
        "click",
        ".wc-msbc-prev-btn",
        this.handlePrevStep.bind(this)
      );

      // Validation formulaires avant passage étape suivante
      $(document).on("submit", "form", this.handleFormSubmit.bind(this));
    },

    // Gestion bouton suivant
    handleNextStep: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const currentStep = parseInt($button.data("step"), 10);
      const shouldValidate = $button.data("validate") === 1;

      console.log("Next step clicked:", { currentStep, shouldValidate });

      // Validation si requise
      if (shouldValidate && !this.validateCurrentStep(currentStep)) {
        this.showMessage("Veuillez remplir tous les champs requis.", "error");
        return;
      }

      // Collecte des données d'étape
      const stepData = this.collectStepData(currentStep);

      // Désactivation du bouton
      this.setButtonLoading($button, true);

      // Appel AJAX
      this.ajaxCall("wc_msbc_next_step", {
        current_step: currentStep,
        step_data: stepData,
        nonce: wcMsbcAjax.next_nonce,
      })
        .done((response) => {
          console.log("Next step success:", response);

          if (response.success) {
            this.showMessage(response.data.message, "success");

            // Redirection si URL fournie
            if (response.data.redirect_url) {
              setTimeout(() => {
                window.location.href = response.data.redirect_url;
              }, 500);
            } else {
              this.updateProgressBar();
            }
          } else {
            this.showMessage(
              response.data.message ||
                "Erreur lors du passage à l'étape suivante.",
              "error"
            );
          }
        })
        .fail((xhr) => {
          console.error("Next step error:", xhr);
          this.showMessage(
            "Erreur de communication. Veuillez réessayer.",
            "error"
          );
        })
        .always(() => {
          this.setButtonLoading($button, false);
        });
    },

    // Gestion bouton précédent
    handlePrevStep: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const currentStep = parseInt($button.data("step"), 10);

      console.log("Prev step clicked:", { currentStep });

      // Désactivation du bouton
      this.setButtonLoading($button, true);

      // Appel AJAX
      this.ajaxCall("wc_msbc_prev_step", {
        current_step: currentStep,
        nonce: wcMsbcAjax.prev_nonce,
      })
        .done((response) => {
          console.log("Prev step success:", response);

          if (response.success) {
            // Redirection si URL fournie
            if (response.data.redirect_url) {
              window.location.href = response.data.redirect_url;
            }
          } else {
            this.showMessage(
              response.data.message ||
                "Erreur lors du retour à l'étape précédente.",
              "error"
            );
          }
        })
        .fail((xhr) => {
          console.error("Prev step error:", xhr);
          this.showMessage(
            "Erreur de communication. Veuillez réessayer.",
            "error"
          );
        })
        .always(() => {
          this.setButtonLoading($button, false);
        });
    },

    // Validation étape courante
    validateCurrentStep: function (step) {
      switch (step) {
        case 1:
          return this.validateStep1();
        case 2:
          return this.validateStep2();
        case 3:
          return this.validateStep3();
        case 4:
          return this.validateStep4();
        default:
          return true;
      }
    },

    // Validation étape 1
    validateStep1: function () {
      // Vérification sélection produit/dates (WC Bookings)
      const hasBookingSelection = $(".wc-bookings-booking-form").length > 0;
      return hasBookingSelection;
    },

    // Validation étape 2
    validateStep2: function () {
      let isValid = true;

      // Validation champs requis
      $("input[required], select[required], textarea[required]").each(
        function () {
          const $field = $(this);
          if (!$field.val() || $field.val().trim() === "") {
            $field.addClass("error");
            isValid = false;
          } else {
            $field.removeClass("error");
          }
        }
      );

      // Validation Gravity Forms si présent
      if (typeof gform !== "undefined") {
        const $form = $(".gform_wrapper form");
        if ($form.length > 0) {
          return $form.valid && $form.valid();
        }
      }

      return isValid;
    },

    // Validation étape 3
    validateStep3: function () {
      // Validation signature (placeholder pour MVP)
      const hasSignature =
        $('input[name="signature_accepted"]').is(":checked") ||
        $(".signature-pad canvas").length > 0;
      return hasSignature;
    },

    // Validation étape 4
    validateStep4: function () {
      // Étape finale - toujours valide si on y arrive
      return true;
    },

    // Collecte des données d'étape
    collectStepData: function (step) {
      const data = {};

      switch (step) {
        case 2:
          // Collecte données formulaire
          $("input, select, textarea").each(function () {
            const $field = $(this);
            const name = $field.attr("name");
            if (name && name !== "nonce") {
              data[name] = $field.val();
            }
          });
          break;

        case 3:
          // Collecte données signature
          data.signature_accepted = $('input[name="signature_accepted"]').is(
            ":checked"
          );
          data.signature_timestamp = Math.floor(Date.now() / 1000);
          break;
      }

      return data;
    },

    // État de chargement des boutons
    setButtonLoading: function ($button, loading) {
      if (loading) {
        $button
          .addClass("loading")
          .prop("disabled", true)
          .data("original-text", $button.text())
          .text($button.data("loading-text") || wcMsbcAjax.messages.loading);
      } else {
        $button
          .removeClass("loading")
          .prop("disabled", false)
          .text($button.data("original-text") || $button.text());
      }
    },

    // Appel AJAX générique
    ajaxCall: function (action, data) {
      return $.ajax({
        url: wcMsbcAjax.ajax_url,
        type: "POST",
        dataType: "json",
        data: $.extend(
          {
            action: action,
          },
          data
        ),
        timeout: 30000,
      });
    },

    // Affichage des messages
    showMessage: function (message, type) {
      // Suppression anciens messages
      $(".wc-msbc-message").remove();

      // Création du message
      const $message = $(
        '<div class="wc-msbc-message ' + type + '">' + message + "</div>"
      );

      // Insertion en haut de page ou avant le premier élément visible
      const $target = $(".wc-msbc-progress").first();
      if ($target.length) {
        $target.before($message);
      } else {
        $("body").prepend($message);
      }

      // Animation d'entrée
      $message.hide().fadeIn(300);

      // Auto-suppression pour les messages de succès
      if (type === "success") {
        setTimeout(() => {
          $message.fadeOut(300, function () {
            $(this).remove();
          });
        }, 3000);
      }

      // Scroll vers le message
      $("html, body").animate(
        {
          scrollTop: $message.offset().top - 20,
        },
        300
      );
    },

    // Mise à jour barre de progression
    updateProgressBar: function () {
      const $progressBar = $(".wc-msbc-progress");
      if (!$progressBar.length) return;

      const currentStep = parseInt($progressBar.data("current-step"), 10);
      const totalSteps = 4;
      const percentage = ((currentStep - 1) / totalSteps) * 100;

      // Mise à jour barre de progression
      $(".wc-msbc-progress-fill").css("width", percentage + "%");

      // Mise à jour états des étapes
      $(".wc-msbc-step").each(function (index) {
        const stepNum = index + 1;
        const $step = $(this);

        $step.removeClass("completed current");

        if (stepNum < currentStep) {
          $step.addClass("completed");
        } else if (stepNum === currentStep) {
          $step.addClass("current");
        }
      });
    },

    // Gestion soumission formulaires
    handleFormSubmit: function (e) {
      const $form = $(e.currentTarget);
      const $nextBtn = $(".wc-msbc-next-btn");

      // Si formulaire dans une étape avec bouton suivant, empêcher soumission normale
      if (
        $nextBtn.length > 0 &&
        $form.find("input, select, textarea").length > 0
      ) {
        e.preventDefault();
        $nextBtn.trigger("click");
      }
    },
  };

  // Initialisation au chargement DOM
  $(document).ready(function () {
    WCMultiStepBookingCheckout.init();
  });

  // Exposition globale pour debug
  window.WCMultiStepBookingCheckout = WCMultiStepBookingCheckout;
})(jQuery);
