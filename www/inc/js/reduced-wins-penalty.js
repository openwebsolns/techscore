/*
 * Filter race list to chosen team.
 */

(function(d) {
    const teamSelect = d.getElementById('team-input');
    const raceSelect = d.getElementById('race-input');
    if (!raceSelect) {
        return;
    }

    teamSelect.addEventListener('change', function(e) {
        const team = teamSelect.value;
        // reset all races
        for (var i = 0; i < raceSelect.options.length; i++) {
            raceSelect.options.item(i).disabled = false;
        }

        if (team !== '') {
            // filter races by chosen team; assume first option is placeholder
            for (var i = 1; i < raceSelect.options.length; i++) {
                const option = raceSelect.options.item(i);
                option.disabled = option.dataset.team1 !== team && option.dataset.team2 !== team;
            }
        }

        // reset chosen race if now disabled
        if (raceSelect.selectedOptions.item(0).disabled) {
            raceSelect.value = '';
            raceSelect.dispatchEvent(new Event('change'));
        }
    }, false);
})(document);
