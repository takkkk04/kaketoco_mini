import { initializeApp } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-app.js";
import {
    getFirestore,
    collection,
    addDoc,
    getDocs,
} from "https://www.gstatic.com/firebasejs/12.6.0/firebase-firestore.js";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
const firebaseConfig = {
    apiKey: 
    authDomain: "kaketoco-001.firebaseapp.com",
    projectId: "kaketoco-001",
    storageBucket: "kaketoco-001.firebasestorage.app",
    messagingSenderId: "822926026136",
    appId: "1:822926026136:web:6e809a33d66841f60cf7ed"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

const COL_NAME = "pesticides";

// =============================================
// firebaseからデータを全件取得
// =============================================

async function fetchAllPesticides() {
    const snap = await getDocs(collection(db, COL_NAME));
    const list = [];
    snap.forEach((doc) => {
        list.push({id: doc.id, ...doc.data()});
    });
    return list; 
};

(async () => {
    const all = await fetchAllPesticides();
    console.log("Firebaseの全マスタデータ", all);
})();
