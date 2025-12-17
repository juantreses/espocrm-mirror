define('custom:views/contact/detail', ['views/detail'], function (Dep) {
    
    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            
            // Listen for after:render event to calculate and display age
            this.listenTo(this.model, 'sync', () => {
                this.displayAge();
                this.renderHrMaxRow();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.displayAge();
            this.renderHrMaxRow();
        },

        displayAge: function () {
            const dateOfBirth = this.model.get('cDateOfBirth');
            
            if (!dateOfBirth) {
                return;
            }

            const age = this.calculateAge(dateOfBirth);
            
            // Find the cDateOfBirth field and add age next to it
            const $dateOfBirthField = this.$el.find('.field[data-name="cDateOfBirth"]');
            
            if ($dateOfBirthField.length) {
                // Remove any existing age display
                $dateOfBirthField.find('.age-display').remove();
                // Ensure any legacy HRmax inline display is removed (moved to its own field row)
                $dateOfBirthField.find('.hrmax-display').remove();
                
                // Add age display
                $dateOfBirthField.append(
                    $('<span>')
                        .addClass('age-display')
                        .css({
                            'margin-left': '10px',
                            'color': '#999',
                            'font-style': 'italic'
                        })
                        .text('(' + age + ')')
                );
            }
        },

        calculateAge: function (dateOfBirth) {
            const birthDate = new Date(dateOfBirth);
            const today = new Date();
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            // Adjust age if birthday hasn't occurred yet this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        },

        calculateMaxHeartRate: function (age) {
            if (!Number.isFinite(age)) return null;
            // Tanaka formula: 208 − 0.7 × age
            const maxHR = 208 - (0.7 * age);
            return Math.max(0, Math.round(maxHR));
        },

        renderHrMaxRow: function () {
            const dateOfBirth = this.model.get('cDateOfBirth');
            const $panelBody = this.$el.find('.panel-body.panel-body-form').first();
            if (!$panelBody.length) return;

            // Remove existing injected row to avoid duplicates
            $panelBody.find('.row.hrmax-row').remove();

            if (!dateOfBirth) return;

            const age = this.calculateAge(dateOfBirth);
            const maxHr = this.calculateMaxHeartRate(age);
            if (!Number.isFinite(maxHr)) return;

            // Find the row containing the DOB field
            const $dobCell = $panelBody.find('.cell[data-name="cDateOfBirth"]').first();
            const $dobRow = $dobCell.closest('.row');

            // Build the HR max row
            const $row = $('<div>').addClass('row hrmax-row');
            const $cell = $('<div>')
                .addClass('cell col-sm-6 form-group')
                .attr('data-name', 'hrMax')
                .attr('tabindex', '-1');

            const $label = $('<label>')
                .addClass('control-label')
                .attr('data-name', 'hrMax')
                .append($('<span>').addClass('label-text').text('Berekende max. hartslag'));

            const $field = $('<div>')
                .addClass('field')
                .attr('data-name', 'hrMax')
                .append($('<span>').addClass('numeric-text').text(maxHr + ' bpm'));

            $cell.append($('<a>')
                .attr('role', 'button')
                .addClass('pull-right inline-edit-link hidden')
                .append($('<span>').addClass('fas fa-pencil-alt fa-sm')));
            $cell.append($label);
            $cell.append($field);
            $row.append($cell);

            if ($dobRow.length) {
                $dobRow.after($row);
            } else {
                // Fallback: append at the end of the panel body
                $panelBody.append($row);
            }
        }
    
    });
});