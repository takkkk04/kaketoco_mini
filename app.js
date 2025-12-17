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

// =============================================
// firebaseからデータを全件取得
// =============================================
const COL_NAME = "pesticides";

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
// +++++++++++++++++++++++++++++++++++++++++++++
// shopify SDK連携（SDK＝API＋いろいろな機能）
// +++++++++++++++++++++++++++++++++++++++++++++
// =============================================

//ドメイン＝住所
const SHOPIFY_DOMAIN = "xn-lckmg7f.myshopify.com";
//shopifyのAPIキー（公開キーだからいちいち消さなくていい）
const SHOPIFY_STOREFRONT_TOKEN = "bc25ee7aec5d0c9de8b934c6f8e0aa90";

let shopifyUI = null;

// =============================================
// ShopifyのSDKを読み込む関数
// SDKとUIは別々に読み込まれるので、両方読み込まれるまで待つ
// =============================================

function loadShopifyBuySDK() {
    return new Promise((resolve, reject) => {
        const scriptURL = "https://sdks.shopifycdn.com/buy-button/latest/buy-button-storefront.min.js";

        //SDKとUIが読み込まれていればOK
        if (window.ShopifyBuy && window.ShopifyBuy.UI) {
            resolve();
            return;
        }

        //SDKだけ読み込まれていたら、UIの読み込みを待つ          
        if (window.ShopifyBuy && !window.ShopifyBuy.UI) {
            const t = setInterval(() => {
                if (window.ShopifyBuy.UI) {
                    clearInterval(t);
                    resolve();
                }
            },50);  //0.5秒ごとにUIが読み込まれたかチェック

            //10秒経ったらタイムアウト
            setTimeout(() => {
                clearInterval(t);
                reject(new Error("ShopifyBuy.UIが読み込めませんでした"));
            },10000);
            return;
        }
        
        //まだなにも読み込まれてない時、SDKの読み込みをしろ
        //まずscriptタグを作れ
        const script = document.createElement("script");
        script.async = true;
        //URLを読みにいけ
        script.src = scriptURL;
        //読み込めたらresolve、失敗したらreject
        script.onload = () => resolve();
        script.onerror = () => reject(new Error("ShopifyBuy SDKの読み込みに失敗しました"));
        //HTMLのheadかbodyにscriptタグをぶちこめ
        (document.head || document.body).appendChild(script);
    });
}

// =============================================
// ShopifyのUIを準備する関数
// =============================================
async function prepareShopifyUI() {
    //UIがすでにあったらそれを返す
    if (shopifyUI) return shopifyUI;
    //SDKとUIがちゃんと読み込まれるまで待つ
    await loadShopifyBuySDK();
    //shopifyに繋ぐ（クライアントを作る）
    const client = window.ShopifyBuy.buildClient({
        domain: SHOPIFY_DOMAIN,
        storefrontAccessToken: SHOPIFY_STOREFRONT_TOKEN,
    });
    //UIが準備できるまで待つ
    shopifyUI = await window.ShopifyBuy.UI.onReady(client);
    return shopifyUI;
}

// =============================================
// shopifyのデータを表示する関数
// =============================================
async function renderBuyButton (node, productID) {
    // productIDがなければ無視
    if (!productID) return;
    //上で作ったUI準備関数を呼ぶ
    const ui = await prepareShopifyUI();

    //UIのコンポーネントを作る
    ui.createComponent("product", {
        //shopifyの商品IDごとに取ってこい
        id: String(productID),
        node,
        moneyFormat: "%7B%7Bamount_no_decimals%7D%7D%E5%86%86",
        options: {
            product: {
                contents: {
                    img: true,
                    title: false,
                    price: true,
                },
                text: {button: "購入"},
            },
            cart: {
                text: {
                    total: "小計",
                    button: "購入手続きへ",
                }
            },
        },
    });
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
        const buyCellId = `buy-${p.id}`;

        //データを表示
        const rowHtml = `
        <tr>
            <td>${escapeHTML(p.name)}</td>
            <td>${escapeHTML(magnification)}</td>
            <td>${escapeHTML(times)}</td>
            <td>${escapeHTML(interval)}</td>
            <td>${escapeHTML(score)}</td>
            <td><div id="${buyCellId}"></div></td>
        </tr>
        `;
        $tbody.append(rowHtml);

        const node = document.getElementById(buyCellId);
        await renderBuyButton(node, p.shopify_id);
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