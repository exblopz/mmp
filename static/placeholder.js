/* 
 * Copyright 2011 by ORCA, Jl. Taman Sulfat 7 No 4, Malang, ID
 * All rights reserved
 * 
 * Written By: herdian ferdianto
 * ferdhie@orca.web.id
 * http://ferdianto.com/
 */


function addEvent( obj, type, fn ) {
  if ( obj.attachEvent ) {
    obj['e'+type+fn] = fn;
    obj[type+fn] = function(){obj['e'+type+fn]( window.event );}
    obj.attachEvent( 'on'+type, obj[type+fn] );
  } else
    obj.addEventListener( type, fn, false );
}

function removeEvent( obj, type, fn ) {
  if ( obj.detachEvent ) {
    obj.detachEvent( 'on'+type, obj[type+fn] );
    obj[type+fn] = null;
  } else
    obj.removeEventListener( type, fn, false );
}

function inputFocus() {
    if (this.value == this.getAttribute('placeholder'))
        this.value='';
}

function inputBlur() {
    if (this.value == '')
        this.value=this.getAttribute('placeholder');
}

addEvent( window, 'load', function() {
    var i = document.createElement("input");
    if (typeof i.placeholder == 'undefined') {
        var inputs = document.getElementsByName("INPUT");
        for(var j=0,len=inputs.length; j<len; j++) {
            if (typeof inputs[j].getAttribute('placeholder') != 'undefined' && inputs[j].type != 'submit' && !inputs[j].value) {
                inputs[j].value = inputs[j].getAttribute("placeholder");
                inputs[j].onfocus = inputFocus;
                inputs[j].onblur = inputBlur;
            }
        }
    }
});