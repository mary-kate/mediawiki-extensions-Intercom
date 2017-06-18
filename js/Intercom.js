/*<![CDATA[*/ 

addOnloadHook(intercomExpiryOption);

function intercomrender(div,response)
{
  var res = eval('(' + response + ')');
  div.innerHTML = res['message'];
  div.setAttribute("class",res['class']); 
}

function intercomExpiryOption()
{
  var expirydrop = document.getElementById('wpExpiry');
  var expiryother = document.getElementById('wpExpiryOther');
  if (expirydrop && expiryother)
  {
    if (expirydrop.value == 'other')
    {
      expiryother.style.display='inline';
    } else {
      expiryother.style.display='none';
    }
  }
}

function nextMessage(id, time)
{
  sajax_do_call('Intercom::getNextMessage',[id, time],nextupdate);
}

function prevMessage(id, time)
{
  sajax_do_call('Intercom::getPrevMessage',[id, time],prevupdate);
}

function prevupdate(req)
{
  if (req.readyState == 4 && req.status == 200)
  {
    var intercommessage = document.getElementById('intercommessage');
    if (!intercommessage) return;
    if (req.responseText != 'false')
    {
      //alert('prev: ' + req.responseText);
      intercomrender(intercommessage,req.responseText);
    }
  } else {
    alert('An error occured:' + req.responseText);
  }
}

function nextupdate(req)
{
  if (req.readyState == 4 && req.status == 200)
  {
    var intercommessage = document.getElementById('intercommessage');
    if (!intercommessage) return;
    if (req.responseText != 'false')
    {
      //alert('next: ' + req.responseText);
      intercomrender(intercommessage,req.responseText);
    }

  } else {
    alert('An error occured:' + req.responseText);
  }
}

function readnextMessage(id,time)
{
  sajax_do_call('Intercom::getNextMessage',[id,time],function(req) {
    if (req.readyState == 4 && req.status == 200)
    {
      if (req.responseText == 'false')
      {
        sajax_do_call('Intercom::getPrevMessage',[id,time],function(req) {
          if (req.readyState == 4 && req.status == 200)
          {
            var intercommessage = document.getElementById('intercommessage');
            if (!intercommessage) return;
            if (req.responseText == 'false')
            {
              intercommessage.style.display = 'none';
            } else {
              //alert(req.responseText);
              intercomrender(intercommessage,req.responseText);
            }
          } else {
            alert('An error occured:' + req.responseText);
          }
        });
      } else {
        //alert(req.responseText);
        intercomrender(intercommessage,req.responseText);
      }
    } else {
    alert('An error occured:' + req.responseText);
  }
      
  });
}

function markreadupdate(req, id,time) 
{
  if (req.readyState == 4 && req.status == 200)
  {
    arr = req.responseText;
    if (arr == 'true')
    {
      readnextMessage(id,time);
    }
  } else {
    alert('An error occured:' + req.responseText);
  }
}

function markRead(id,time)
{
  sajax_do_call('Intercom::markRead',[id],function(req) { markreadupdate(req,id,time) });
}

/*]]>*/
