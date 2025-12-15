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
// HTMLのセレクトボックスの中身を作る関数
// =============================================
function populateSelect($select, values) {
    //最初の「指定なし」だけ残す
    $select.find("option:not(:first)").remove();
    //プルダウンの中に作物名、病害虫名をぶちこむ
    values.forEach((v) => {
        const option = `<option value="${v}">${v}</option>`;
        $select.append(option);
    })
}

// =============================================
// プルダウンの中身をfirebaseから取ってくる
// =============================================
async function setupPulldowns() {
    const all = await fetchAllPesticides();

    //Setは重複を自動で消してくれる
    const cropSet = new Set();
    const targetSet = new Set();

    //全データから作物名、病害虫名の配列をSetに入れる→重複が消える
    all.forEach((p) => {
        if (Array.isArray(p.crop)) {
            p.crop.forEach((c) => cropSet.add(c));
        }

        if (Array.isArray(p.target)) {
            p.target.forEach((t) => targetSet.add(t));
        }        
    });

    //Setを配列に戻して、ついでにソートもかける
    const crops = Array.from(cropSet).sort();
    const targets = Array.from(targetSet).sort();

    //上のセレクトボックス作る関数に入れる
    populateSelect($("#crop"),crops);
    populateSelect($("#target"),targets);
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
            const crops = Array.isArray(p.crop) ? p.crop : [];
            if (!crops.includes(crop)) return false;
        }
        //病害虫選択、未選択でもtrue
        if (target) {
            const targets = Array.isArray(p.target) ? p.target : [];
            if (!targets.includes(target)) return false;
        }
        return true;
    });

    await renderResults(filtered);
}

$(function(){
    //プルダウンの中身セットアップ
    setupPulldowns();

    //検索ボタンクリックイベント
    $("#search_btn").on("click",async function(){
        try {
            await handleSearch();
        } catch (e){
            console.error(e);
            alert("データの取得に失敗しました");
        }
    });

    //リセットボタンクリックイベント
    $("#reset_btn").on("click", function(){
        $("#result_table tbody").empty();
        $("#result_count").text("0件")
    });
});