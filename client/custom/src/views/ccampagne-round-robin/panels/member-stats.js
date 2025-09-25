define([
    'views/record/panels/bottom'
], (BottomPanelView) => {

    return class extends BottomPanelView {
        
        templateContent = `
{{#if totalLeads}}
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Naam</th>
                <th class="text-center">Aantal Leads</th>
                <th class="text-center">Verdeling</th>
            </tr>
        </thead>
        <tbody>
            {{#each members}}
            <tr>
                <td>{{memberName}}</td>
                <td class="text-center">{{leadCount}}</td>
                <td class="text-center">{{percentage}}%</td>
            </tr>
            {{/each}}
        </tbody>
        <tfoot>
            <tr style="border-top: 2px solid #ddd;">
                <td><strong>Total</strong></td>
                <td class="text-center"><strong>{{totalLeads}}</strong></td>
                <td class="text-center"><strong>100%</strong></td>
            </tr>
        </tfoot>
    </table>
{{else}}
    <div class="text-muted text-center" style="padding: 20px;">
        Geen actieve deelnemers of leads voor deze round robin.
    </div>
{{/if}}
        `
        
        setup() {
            super.setup();
            this.panelId = 'roundRobinStats' + this.model.attributes.id;
            
            // Initialize data
            this.hasData = false;
            this.loading = false;
            this.statisticData = {}; 
            
            // Load weight data
            this.loadStatistics();
        }
        
        loadStatistics() {
            this.loading = true;
            this.reRender();
            
            const recordId = this.model.id;
            const url = `CCampagneRoundRobin/${recordId}/member-stats`;
            
            // The URL is now correctly passed to the AJAX request
            Espo.Ajax.getRequest(url)
                .then(response => {
                    // Sort the data by leadCount ascending (a.leadCount - b.leadCount)
                    const sortedMembers = (response.members || []).sort((a, b) => {
                        return a.leadCount - b.leadCount;
                    });

                    if (response.totalLeads > 0) {
                        sortedMembers.forEach(member => {
                            member.percentage = ((member.leadCount / response.totalLeads) * 100).toFixed(1);
                        });
                    } else {
                        sortedMembers.forEach(member => {
                            member.percentage = '0.0';
                        });
                    }

                    this.statisticData = {
                        members: sortedMembers,
                        totalLeads: response.totalLeads,
                    };
                    this.hasData = (response.totalLeads > 0 || sortedMembers.length > 0);
                })
                .catch(error => {
                    console.error('Error loading statistic data:', error);
                    this.hasData = false;
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }
        
        afterRender() {
            super.afterRender();
        }
        
        data() {
            return {
                ...super.data(),
                hasData: this.hasData,
                loading: this.loading,
                panelId: this.panelId,
                ...this.statisticData
            };
        }
        
        remove() {
            super.remove();
        }
    }
});