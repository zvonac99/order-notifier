;(function (root, factory) {
  if (typeof exports === 'object' && typeof module === 'object') {
    module.exports = factory();
  } else if(typeof define === 'function' && define.amd) {
    define([], factory);
  } else if(typeof exports === 'object') {
    exports['notifier'] = factory();
  } else {
    root['notifier'] = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  var count = 0;
  var d = document;


  var myCreateElement = function(elem, attrs) {
    var el = d.createElement(elem);
    for (var prop in attrs) {
      el.setAttribute(prop, attrs[prop]);
    }
    return el;
  };

  /* Orginalnna funkcija */
  /* var createContainer = function() {
    var container = myCreateElement('div', {class: 'notifier-container', id: 'notifier-container'});
    d.body.appendChild(container);
  }; */ 

  var createContainer = function(name) {
    var id = 'notifier-container-' + name;
    if (!d.getElementById(id)) {
      var container = myCreateElement('div', {
        class: 'notifier-container notifier-container-' + name,
        id: id
      });
      d.body.appendChild(container);
    }
  };


  // var show = function(title, msg, type, icon, timeout, position, maxNotifications) {
  // Promjena prema proširenom kreiranju kontenjera sa pozicijom i maksimalnim brojem poruka za prikaz
  var show = function(title, msg, type, icon, timeout, position, maxNotifications = 5) {

    if (!position) position = 'top-right'; // defaultna pozicija

    createContainer(position);
    // var container = d.getElementById('notifier-container-' + position);
    //---------- Kraj prepravljenog djela -----------------------------


    if (typeof timeout != 'number') timeout = 0;

    var ntfId = 'notifier-' + count;

    var container = d.querySelector('.notifier-container-' + position),
        ntf       = myCreateElement('div', {class: 'notifier ' + type}),
        ntfTitle  = myCreateElement('h2',  {class: 'notifier-title'}),
        ntfBody   = myCreateElement('div', {class: 'notifier-body'}),
        ntfImg    = myCreateElement('div', {class: 'notifier-img'}),
        img       = myCreateElement('img', {class: 'img', src: icon}),
        ntfClose  = myCreateElement('button',{class: 'notifier-close', type: 'button'});

    ntfTitle.innerHTML = title;
    ntfBody.innerHTML  = msg;
    ntfClose.innerHTML = '&times;';

    if (icon.length > 0) {
      ntfImg.appendChild(img);
    }

    ntf.appendChild(ntfClose);
    ntf.appendChild(ntfImg);
    ntf.appendChild(ntfTitle);
    ntf.appendChild(ntfBody);

    container.appendChild(ntf);

    ntfImg.style.height = ntfImg.parentNode.offsetHeight + 'px' || null;

    setTimeout(function() {
      ntf.className += ' shown';
      ntf.setAttribute('id', ntfId);
    }, 100);

    // Ako ima više notifikacija od maxNotifications, zatvori najstariju
    if (container.children.length > maxNotifications) {
      // Prvo dijete kontejnera je najstarija notifikacija
      var oldestNotification = container.children[0];
      if (oldestNotification) {
        hide(oldestNotification.id);
      }
    }

    if (timeout > 0) {

      setTimeout(function() {
        hide(ntfId);
      }, timeout);

    }

    ntfClose.addEventListener('click', function() {
      hide(ntfId);
    });

    count += 1;

    return ntfId;

  };

 var hide = function(notificationId) {
  var notification = document.getElementById(notificationId);

  if (notification) {
    // 1. Ukloni klasu "shown" (vidljivo) ako je prisutna
    notification.classList.remove('shown');

    // 2. Dodaj klasu "hide" da pokrene animaciju izlaza
    notification.classList.add('hide');

    // 3. Pričekaj trajanje animacije (mora odgovarati CSS tranziciji), pa ukloni iz DOM-a
    setTimeout(function() {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 400); // Vrijeme mora odgovarati CSS animaciji (.4s)

    return true;
  } else {
    return false;
  }
};

  // Orginal
  // createContainer();

  return {
    show: show,
    hide: hide
  };
}));