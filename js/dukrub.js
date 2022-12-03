M.gradingform_dukrub = {};

/**
 * This function is called for each dukrub on page.
 */
M.gradingform_dukrub.init = function(Y, options) {
    Y.on('click', M.gradingform_dukrub.levelclick, '#dukrub-'+options.name+' .level', null, Y, options.name);
    // Capture also space and enter keypress.
    Y.on('key', M.gradingform_dukrub.levelclick, '#dukrub-' + options.name + ' .level', 'space', Y, options.name);
    Y.on('key', M.gradingform_dukrub.levelclick, '#dukrub-' + options.name + ' .level', 'enter', Y, options.name);

    Y.all('#dukrub-'+options.name+' .radio').setStyle('display', 'none')
    Y.all('#dukrub-'+options.name+' .level').each(function (node) {
      if (node.one('input[type=radio]').get('checked')) {
        node.addClass('checked');
      }
    });
};

M.gradingform_dukrub.levelclick = function(e, Y, name) {
    var el = e.target
    while (el && !el.hasClass('level')) el = el.get('parentNode')
    if (!el) return
    e.preventDefault();
    el.siblings().removeClass('checked');

    // Set aria-checked attribute for siblings to false.
    el.siblings().setAttribute('aria-checked', 'false');
    chb = el.one('input[type=radio]')
    if (!chb.get('checked')) {
        chb.set('checked', true)
        el.addClass('checked')
        // Set aria-checked attribute to true if checked.
        el.setAttribute('aria-checked', 'true');
    } else {
        el.removeClass('checked');
        // Set aria-checked attribute to false if unchecked.
        el.setAttribute('aria-checked', 'false');
        el.get('parentNode').all('input[type=radio]').set('checked', false)
    }

    // harald.bamberger@donau-uni.ac.at 20190506
    var sum = 0;
    $('table#advancedgrading-criteria td.checked input[type!="radio"]').each(function(idx, data) { 
      sum += parseFloat(data.value); 
    });

    var currentgrade = $('span.currentgrade')[0];

    if( $('span#currentgradelabel').length < 1 ) {
      var cglabel = $("<span id='currentgradelabel'>gespeichert: </span>");
      currentgrade.before( cglabel[0] );
    }

    if( $('span#otfgrade').length < 1 ) {
      var livesum = jQuery("<span id='otfgrade'></span>");
      currentgrade.after( livesum[0] );
    }
    $('span#otfgrade').text(' (live: ' + sum + ')');
}
