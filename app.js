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
        list.push({ id: doc.id, ...doc.data() });
    });
    return list;
};

(async () => {
    const all = await fetchAllPesticides();
    console.log("Firebaseの全マスタデータ", all);
})();

// =============================================
// HTMLエスケープ,だいたい入れとくもん、コピペでOKぽい
// &とか<とかそのまま入力したら事故るので変換する
// =============================================
function escapeHTML(str) {
    if (str === null || str === undefined) return "";
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

// =============================================
// 検索結果表示
// =============================================
async function renderResults (items) {
    const $tbody = $("#result_table tbody");
    $tbody.empty();

    // 件数表示
    $("#result_count").text(`${items.length}件`);

    for (let i = 0; i < items.length; i++) {
        const p = items[i];

        //データがnullの場合だけ空文字に変換
        const magnification = (p.magnification ?? "");
        const times = (p.times ?? "");
        const interval = (p.interval ?? "");
        const score = (p.score ?? "");

        //データを表示
        const rowHtml = `
        <tr>
            <td>${escapeHTML(p.name)}</td>
            <td>${escapeHTML(magnification)}</td>
            <td>${escapeHTML(times)}</td>
            <td>${escapeHTML(interval)}</td>
            <td>${escapeHTML(score)}</td>
            <td>(あとで購入ボタン)</td>
        </tr>
        `;
        $tbody.append(rowHtml);           
    };
}

// =============================================
// 検索ボタンクリックイベント
// =============================================

// データ取得して表示
async function handleSearch() {
    const category = $("#category").val();
    const crop = $("#crop").val();
    const target = $("#target").val();
    const all = await fetchAllPesticides();

    const filtered = all.filter((p) =>{
        //カテゴリ選択、なしはfalse
        if (p.category !== category) return false;
        //作物選択、未選択でもtrue
        if (crop) {
            const crops = Array.isArray(p.crops) ? p.crops : [];
            if (!crops.includes(crop)) return false;
        }
        //病害虫選択、未選択でもtrue
        if (target) {
            const targets = Array.isArray(p.targets) ? p.targets : [];
            if (!targets.includes(target)) return false;
        }
        return true;
    });

    await renderResults(filtered);
}

$(function(){
    $("#search_btn").on("click",async function(){
        try {
            await handleSearch();
        } catch (e){
            console.error(e);
            alert("データの取得に失敗しました");
        }
    });

    $("#reset_btn").on("click", function(){
        $("#result_table tbody").empty();
        $("#result_count").text("0件")
    });
});