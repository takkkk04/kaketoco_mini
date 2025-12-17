import { initializeApp } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-app.js";
import {
    getFirestore,
    collection,
    addDoc,
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

//カンマ区切りの入力を配列に変換
function parseList (str) {
    if (!str) return[];
    return str
    .split(",")
    .map(s => s.trim())
    .filter(s => s !=="");
}

$(function(){
    $("#save_btn").on("click", async function(){
        const number = $("#number").val();
        const name = $("#name").val();
        const category = $("#category").val();
        const crop = $("#crop").val();
        const target = $("#target").val();
        const interval = $("#interval").val();
        const magnification = $("#magnification").val();
        const times = $("#times").val();
        const score = $("#score").val();
        const shopify_id = $("#shopify_id").val();

        const docData = {
            number: Number(number),
            name,
            category,
            crop: parseList($("#crop").val()),
            target: parseList($("#target").val()),
            interval: interval ? Number(interval) : null,
            magnification: magnification ? Number(magnification) : null,
            times: times ? Number(times) : null,
            score: score ? Number(score) : null,
            shopify_id: shopify_id || null,
        };

        console.log("保存データ", docData);

        try {
            await addDoc(collection(db, "pesticides"),docData);
            alert("保存しました");
            $("#pesticide_form")[0].reset();
        } catch (err) {
            console.error(err);
            alert("保存できませんでした")
        }       
    });
});