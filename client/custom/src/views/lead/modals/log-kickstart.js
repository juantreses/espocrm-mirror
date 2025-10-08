define('custom:views/lead/modals/log-kickstart', ['views/modal'], function (Dep) {
    
    return Dep.extend({
        
        template: 'custom:lead/modals/log-kickstart',
        
        className: 'dialog dialog-record',
        
        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Log Kickstart',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Annuleer'
                }
            ];
            
            this.headerText = 'Log Kickstart - ' + this.model.get('name');
        },

        afterRender: function () {
			Dep.prototype.afterRender.call(this);

			const $outcome = this.$el.find('[name="outcome"]');
			const $callAgainField = this.$el.find('[data-field="call-again-date"]');

			$outcome.on('change', () => {
				const value = $outcome.val();
				if (value === 'still_thinking') {
					$callAgainField.show();
				} else {
					$callAgainField.hide();
				}
			});
		},
        
        actionSave: function () {
            const outcome = this.$el.find('[name="outcome"]').val();
            const kickstartDateTime = this.$el.find('[name="kickstartDateTime"]').val();
            const callAgainDateTime = this.$el.find('[name="callAgainDateTime"]').val();
            const coachNote = this.$el.find('[name="coachNote"]').val();

            const saveButton = this.$el.find('button[data-name="save"]');
            saveButton.prop('disabled', true);

            if (!outcome) {
                Espo.Ui.error('Selecteer een uitkomst.');
                saveButton.prop('disabled', false);
                return;
            }

            if (outcome === 'still_thinking' && !callAgainDateTime) {
				if (!callAgainDateTime) {
                    Espo.Ui.error('Datum/tijd opnieuw bellen is verplicht.');
                    saveButton.prop('disabled', false);
                    return;
                }
                const now = new Date();
                const callAgainDate = new Date(callAgainDateTime);
                if (callAgainDate <= now) {
                    Espo.Ui.error('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
			}

            if (kickstartDateTime) {
                const now = new Date();
                const ksDate = new Date(kickstartDateTime);
                if (ksDate > now) {
                    Espo.Ui.error('Datum/tijd van kickstart mag niet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }
            
            Espo.Ajax.postRequest(`lead/action/logKickstart`, {
                id: this.model.id,
                outcome: outcome,
                kickstartDateTime: kickstartDateTime,
                callAgainDateTime: callAgainDateTime || null,
                coachNote: coachNote || null
            }).then((response) => {
                this.trigger('success', response);
                this.close();
            }).catch(() => {
                Espo.Ui.error('Failed to log kickstart');
                saveButton.prop('disabled', false);
            });
        }
    });
});