import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
import { getAuth, signInWithEmailAndPassword, signOut, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-auth.js";
import { getFunctions, httpsCallable } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-functions.js";
import { getFirestore, doc, getDoc, updateDoc } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-firestore.js";
import { firebaseConfig } from "./firebase-config.js";

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const functions = getFunctions(app, "us-central1");
const db = getFirestore(app);

const $ = (id)=>document.getElementById(id);
const log = (msg)=>{ const el=$("log"); el.innerText += msg + "\n"; el.scrollTop = el.scrollHeight; };

let current = { orgId:null, eventId:null, eventCode:null };

$("btnHostSignIn").onclick = async () => {
  try {
    await signInWithEmailAndPassword(auth, $("hostEmail").value.trim(), $("hostPass").value);
  } catch (e) {
    $("hostAuth").innerHTML = `<span class="error">${e.message}</span>`;
  }
};
$("btnHostSignOut").onclick = async () => { await signOut(auth); };

onAuthStateChanged(auth, async (user) => {
  if (user) {
    const tt = await user.getIdTokenResult(true);
    const claims = tt.claims || {};
    $("hostAuth").innerHTML = `
      <span>Signed in: ${user.email}</span>
      <span>orgId: ${claims.orgId || "(none)"} (if just added, sign out/in)</span>
    `;
    current.orgId = claims.orgId || null;
  } else {
    $("hostAuth").innerHTML = `<span>Signed out</span>`;
    current = { orgId:null, eventId:null, eventCode:null };
    $("eventInfo").innerHTML = "";
    $("voteState").innerHTML = "";
  }
});

$("btnCreateEvent").onclick = async () => {
  if (!current?.orgId) { $("eventInfo").innerHTML = `<span class="error">No org claim on your account.</span>`; return; }
  $("eventInfo").textContent = "Creating...";
  try {
    const hostCreateEvent = httpsCallable(functions, "hostCreateEvent");
    const res = await hostCreateEvent({
      venueName: $("venueName").value.trim(),
      pin: $("eventPin").value.trim()
    });
    const { eventId, eventCode } = res.data || {};
    current.eventId = eventId;
    current.eventCode = eventCode;
    $("eventInfo").innerHTML = `
      <div>Event created ✅</div>
      <div>eventId: <span class="mono">${eventId}</span></div>
      <div>eventCode (share with guests): <strong>${eventCode}</strong></div>
    `;
    await refreshState();
  } catch (e) {
    $("eventInfo").innerHTML = `<span class="error">${e.message}</span>`;
  }
};

$("btnRefreshEvent").onclick = refreshState;

async function refreshState(){
  if (!current.orgId || !current.eventId) { $("voteState").textContent = "No event yet."; return; }
  const ref = doc(db, `tenants/${current.orgId}/events/${current.eventId}/state`);
  const snap = await getDoc(ref);
  $("voteState").textContent = snap.exists() ? JSON.stringify(snap.data(), null, 2) : "state missing";
}

$("btnOpen").onclick = async () => {
  if (!current.orgId || !current.eventId) return;
  try {
    const ref = doc(db, `tenants/${current.orgId}/events/${current.eventId}/state`);
    const endsAt = Date.now() + 90_000;
    await updateDoc(ref, {
      "voting.open": true,
      "voting.endsAt": endsAt,
      "voting.counts.encore": 0,
      "voting.counts.another": 0,
      "voting.counts.maybe": 0
    });
    await refreshState();
  } catch (e) { log(`Open err: ${e.message}`); }
};

$("btnExtend").onclick = async () => {
  if (!current.orgId || !current.eventId) return;
  try {
    const ref = doc(db, `tenants/${current.orgId}/events/${current.eventId}/state`);
    const snap = await getDoc(ref);
    const now = Date.now();
    const curEnd = snap.data()?.voting?.endsAt || now;
    const newEnd = Math.max(curEnd, now) + 30_000;
    await updateDoc(ref, {
      "voting.endsAt": newEnd,
      "voting.extendCount": (snap.data()?.voting?.extendCount || 0) + 1
    });
    await refreshState();
  } catch (e) { log(`Extend err: ${e.message}`); }
};

$("btnClose").onclick = async () => {
  if (!current.orgId || !current.eventId) return;
  try {
    const ref = doc(db, `tenants/${current.orgId}/events/${current.eventId}/state`);
    await updateDoc(ref, { "voting.open": false });
    await refreshState();
  } catch (e) { log(`Close err: ${e.message}`); }
};
