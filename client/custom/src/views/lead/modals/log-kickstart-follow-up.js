define('custom:views/lead/modals/log-kickstart-follow-up', ['views/modal', 'custom:utils/date-utils'], function (Dep, DateUtils) {

	return Dep.extend({
		template: 'custom:lead/modals/log-kickstart-follow-up',
		className: 'dialog dialog-record',

		setup: function () {
			this.buttonList = [
				{
					name: 'save',
					label: 'Opslaan',
					style: 'primary'
				},
				{
					name: 'cancel',
					label: 'Annuleer'
				}
			];

			this.headerText = 'Log KS Opvolging - ' + this.model.get('name');
		},

		actionSave: function () {
			const outcome = this.$el.find('[name="outcome"]').val();
			const coachNote = this.$el.find('[name="coachNote"]').val();
			const followUpDateTime = this.$el.find('[name="followUpDateTime"]').val();

            const saveButton = this.$el.find('button[data-name="save"]');
            saveButton.prop('disabled', true);

			if (!outcome) {
                Espo.Ui.error('Selecteer een uitkomst.');
				saveButton.prop('disabled', false);
                return;
            }

			if (followUpDateTime) {
                const now = new Date();
                const ksDate = new Date(followUpDateTime);
                if (ksDate > now) {
                    Espo.Ui.error('Datum/tijd van opvolging mag niet in de toekomst zijn.');
					saveButton.prop('disabled', false);
                    return;
                }
            }

			Espo.Ajax.postRequest('lead/action/logKickstartFollowUp', {
				id: this.model.id,
				outcome: outcome,
				followUpDateTime: followUpDateTime ? DateUtils.toOffsetISOString(new Date(followUpDateTime)) : null,
				coachNote: coachNote || null
			}).then(() => {
				this.trigger('success');
				this.close();
			}).catch(() => {
				Espo.Ui.error('KS Opvolging opslaan mislukt.');
				saveButton.prop('disabled', false);
			});
		}
	});
});
