define('custom:views/lead/modals/log-call', ['views/modal', 'custom:utils/date-utils'], function (Dep, DateUtils) {
    
    return Dep.extend({
        
        template: 'custom:lead/modals/log-call',
        
        className: 'dialog dialog-record',
        
        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Log Gesprek',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Annuleer'
                }
            ];
            
            this.headerText = 'Log Gesprek - ' + this.model.get('name');
        },
        
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            const $outcome = this.$el.find('[name="outcome"]');
            const $callAgainField = this.$el.find('[data-field="call-again-date"]');
            
            $outcome.on('change', () => {
                const value = $outcome.val();
                if (value === 'call_again') {
                    $callAgainField.show();
                } else {
                    $callAgainField.hide();
                }
            });
        },
        
        actionSave: function () {
            const outcome = this.$el.find('[name="outcome"]').val();
            const callDateTime = this.$el.find('[name="callDateTime"]').val();
            const callAgainDateTime = this.$el.find('[name="callAgainDateTime"]').val();
            const coachNote = this.$el.find('[name="coachNote"]').val();

            const saveButton = this.$el.find('button[data-name="save"]');
            saveButton.prop('disabled', true);
        
            if (!outcome) {
                Espo.Ui.error('Selecteer een uitkomst.');
                saveButton.prop('disabled', false);
                return;
            }

            if (outcome === 'call_again' && !callAgainDateTime) {
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

            if (callDateTime) {
                const now = new Date();
                const callDate = new Date(callDateTime);
                if (callDate > now) {
                    Espo.Ui.error('Datum/tijd van gesprek mag niet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }
            
            Espo.Ajax.postRequest(`lead/action/logCall`, {
                id: this.model.id,
		        outcome: outcome,
                callDateTime: callDateTime ? DateUtils.toOffsetISOString(new Date(callDateTime)) : null,
                callAgainDateTime: callAgainDateTime ? DateUtils.toOffsetISOString(new Date(callAgainDateTime)) : null,
                coachNote: coachNote || null
            }).then(() => {
                this.trigger('success');
                this.close();
            }).catch(() => {
                Espo.Ui.error('Failed to log call');
                saveButton.prop('disabled', false);
            });
        }
    });
});
