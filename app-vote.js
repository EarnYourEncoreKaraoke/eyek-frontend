import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
import { getFunctions, httpsCallable } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-functions.js";
import { firebaseConfig } from "./firebase-config.js";

const app = initializeApp(firebaseConfig);
const functions = getFunctions(app, "us-central1");

const $ = (id)=>document.getElementById(id);
const log = (msg)=>{ const el=$("log"); el.innerText += msg + "\n"; el.scrollTop = el.scrollHeight; };

function getDeviceId(){
  let id = localStorage.getItem("eyek_device_id");
  if (!id) {
    id = crypto.getRandomValues(new Uint32Array(4)).join("-");
    localStorage.setItem("eyek_device_id", id);
  }
  return id;
}

async function vote(choice){
  $("voteMsg").textContent = "Submitting...";
  try {
    const castVote = httpsCallable(functions, "castVote");
    const res = await castVote({
      eventCode: $("eventCode").value.trim(),
      choice,
      pin: $("votePin").value.trim(),
      deviceId: getDeviceId()
    });
    $("voteMsg").innerHTML = `<span class="success">Vote recorded ✅</span>`;
    log(`castVote => ${JSON.stringify(res.data)}`);
  } catch (e) {
    $("voteMsg").innerHTML = `<span class="error">${e.message}</span>`;
    log(`ERROR castVote => ${e.message}`);
  }
}

$("btnEncore").onclick = ()=>vote("encore");
$("btnAnother").onclick = ()=>vote("another");
$("btnMaybe").onclick = ()=>vote("maybe");

// Optional: support ?code=XXXX in URL
const params = new URLSearchParams(location.search);
if (params.get("code")) $("eventCode").value = params.get("code");
