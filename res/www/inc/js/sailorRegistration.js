/*
 * Modify the sailor registration form for optimal user experience.
 */

window.addEventListener('load', function(e) {
    var form = document.getElementById('sailor-registration-form');
    if (!form) {
	return 'No form';
    }

    var copySchoolContactInfo = function() {
	var schoolContact = document.getElementById('school-contact');
	var homeContact = document.getElementById('home-contact');
	if (!schoolContact || !homeContact) {
	    return 'Missing contact section';
	}
	var homeEntries = homeContact.querySelectorAll('div.form_entry');
	if (homeEntries.length === 0) {
	    return 'No home contact entries';
	}
	var firstHomeEntry = homeEntries.item(0);

	var p = document.createElement('p');
	var button = document.createElement('button');
	button.type = 'button';
	p.appendChild(button);
	button.appendChild(document.createTextNode('Copy school contact info'));
	button.onclick = function(event) {
	    var inputs = homeContact.querySelectorAll('input');
	    for (var i = 0; i < inputs.length; i++) {
		var input = inputs.item(i);
		var name = input.name.replace(/home/, 'school');
		var sourceInput = schoolContact.querySelector('input[name="' + name + '"]');
		if (sourceInput) {
		    input.value = sourceInput.value;
		}
	    }

	    var selects = homeContact.querySelectorAll('select');
	    for (var i = 0; i < selects.length; i++) {
		var select = selects.item(i);
		var name = select.name.replace(/home/, 'school');
		var sourceSelect = schoolContact.querySelector('select[name="' + name + '"]');
		if (sourceSelect) {
		    select.selectedIndex = sourceSelect.selectedIndex;
		}
	    }
	    event.preventDefault();
	};
	firstHomeEntry.parentNode.insertBefore(p, firstHomeEntry);
    };

    copySchoolContactInfo();
}, false);
