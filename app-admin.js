import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
import { getAuth, signInWithEmailAndPassword, signOut, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-auth.js";
import { getFunctions, httpsCallable } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-functions.js";
import { firebaseConfig } from "./firebase-config.js";

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const functions = getFunctions(app, "us-central1");

const $ = (id)=>document.getElementById(id);
const log = (msg)=>{ const el=$("log"); el.innerText += msg + "\n"; el.scrollTop = el.scrollHeight; };

$("btnAdminSignIn").onclick = async () => {
  try {
    const email = $("adminEmail").value.trim();
    const pass = $("adminPass").value;
    await signInWithEmailAndPassword(auth, email, pass);
  } catch (e) {
    $("authStatus").innerHTML = `<span class="error">Sign in error: ${e.message}</span>`;
  }
};

$("btnAdminSignOut").onclick = async () => {
  await signOut(auth);
};

onAuthStateChanged(auth, async (user) => {
  if (user) {
    const token = await user.getIdTokenResult(true);
    const claims = token.claims || {};
    $("authStatus").innerHTML = `
      <span>Signed in: ${user.email}</span>
      <span>emailVerified: ${user.emailVerified}</span>
      <span>claims: ${JSON.stringify(claims)}</span>
    `;
  } else {
    $("authStatus").innerHTML = `<span>Signed out</span>`;
  }
});

$("btnCreateOrg").onclick = async () => {
  $("orgMsg").textContent = "Creating...";
  try {
    const adminCreateOrg = httpsCallable(functions, "adminCreateOrg");
    const orgId = $("orgId").value.trim();
    const name = $("orgName").value.trim();
    const res = await adminCreateOrg({ orgId, name });
    $("orgMsg").innerHTML = `<span class="success">Org created: ${orgId}</span>`;
    log(`adminCreateOrg => ${JSON.stringify(res.data)}`);
  } catch (e) {
    $("orgMsg").innerHTML = `<span class="error">${e.message}</span>`;
    log(`ERROR adminCreateOrg => ${e.message}`);
  }
};

$("btnAddHost").onclick = async () => {
  $("hostMsg").textContent = "Adding host...";
  try {
    const adminAddHost = httpsCallable(functions, "adminAddHost");
    const email = $("hostEmail").value.trim();
    const orgId = $("hostOrgId").value.trim();
    const res = await adminAddHost({ email, orgId, role: "host" });
    $("hostMsg").innerHTML = `<span class="success">Host added: ${email} → ${orgId}</span><br/><span class="small">Ask the host to sign out and back in to refresh claims.</span>`;
    log(`adminAddHost => ${JSON.stringify(res.data)}`);
  } catch (e) {
    $("hostMsg").innerHTML = `<span class="error">${e.message}</span>`;
    log(`ERROR adminAddHost => ${e.message}`);
  }
};
