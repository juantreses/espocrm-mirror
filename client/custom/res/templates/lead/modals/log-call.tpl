<div class="panel panel-default">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Gesprek uitkomst *</label>
                    <select name="outcome" class="form-control" required>
                        <option value="">-- Selecteer uitkomst --</option>
                        <option value="invited">Afspraak ingepland</option>
			            <option value="call_again">Contact - Terugbellen</option>
                        <option value="no_answer">Geen antwoord</option>
                        <option value="wrong_number">Verkeerd nummer</option>
                        <option value="not_interested">Geen interesse</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6" data-field="meeting-type" style="display: none;">
                <div class="form-group">
                    <label>Type afspraak *</label>
                    <select name="meetingType" class="form-control">
                        <option value="">-- Selecteer type --</option>
                        <option value="kickstart">Kickstart</option>
                        <option value="bws">BWS (skin)</option>
                        <option value="spark">SPARK</option>
                        <option value="iom">IOM</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Gesprek datum/tijd</label>
                    <input type="datetime-local" name="callDateTime" class="form-control" />
                    <small class="form-text text-muted">Laat leeg voor huidige tijd</small>
                </div>
            </div>
            <div class="col-md-6" data-field="call-again-date" style="display: none;">
                <div class="form-group">
                    <label>Datum/tijd opnieuw bellen *</label>
                    <input type="datetime-local" name="callAgainDateTime" class="form-control" />
                    <small class="form-text text-muted">Vul in wanneer opnieuw te bellen</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>Aanvullende notitie</label>
                    <textarea name="coachNote" class="form-control" rows="4" placeholder="Voeg een extra notitie toe..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
