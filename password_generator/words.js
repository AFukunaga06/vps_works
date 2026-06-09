'use strict';

/* ==================================================
   英単語辞書 — 約200語（ランダムモード・単語ベース用）
   ================================================== */
const ENGLISH_WORDS = [
  // 自然・風景
  'alpine','amber','anchor','apex','arctic','arrow','ash','atlas','autumn',
  'beach','beacon','birch','blade','bloom','boulder','brave','breeze','bridge',
  'canyon','cedar','chain','cliff','cloud','coast','coral','crane','creek','crystal',
  'dawn','delta','drift','dune','dusk',
  'eagle','echo','ember','epoch','equinox',
  'falcon','fern','field','fjord','flame','flint','flood','flora','flume','foam','forge','frost',
  'gale','garnet','geyser','glacier','glade','glint','grove',
  'harbor','haven','hedge','helm','horizon','hummock',
  'iris','island','ivory',
  'jasper','jungle',
  'kelp','knoll',
  'lagoon','lance','larch','lark','lava','ledge','lemon','lilac','lodge','lunar',
  'maple','marble','marsh','mast','meadow','mesa','mist','mocha','moor','mossy','mount',
  'nave','nimbus','noble','nordic','north',
  'obsidian','ocean','olive','onyx','opal','orbit','orchid',
  'parrot','pebble','petal','pilot','pine','pixel','plain','plum','polar','poplar','prism',
  'quartz','quest',
  'raven','rapid','realm','reef','ridge','river','rocky','rover','royal','ruby',
  'safari','sage','sapphire','scout','shore','sierra','silver','slate','slope','solar',
  'spark','spire','spray','sprint','stable','stag','starling','storm','stream','summit',
  'talon','teal','thorn','tiger','timber','titan','topaz','torch','tower','trail','trout',
  'ultra',
  'valley','vapor','vault','veil','vibrant','violet',
  'walnut','wave','willow','winter','wren',
  // 動作・抽象
  'blend','boost','build','burst',
  'chase','craft','create',
  'dance','design','direct','drive',
  'expand','explore',
  'focus','force','found',
  'glide','guide',
  'ideal','ignite','impact','inspire',
  'launch','learn','level',
  'merge','method','model','motion',
  'orbit',
  'pivot','power','prime','proof',
  'reach','relay','remix','renew','reset',
  'scale','shift','signal','skill','solve','speed','stride','strong','style','surge','swift',
  'think','thrive','trace','trust',
  'unite','unlock','uplift',
  'valid','venture','vision',
  'watch','whole',
  'yield',
  'zenith',
];

/* ==================================================
   ひらがなワード辞書 — 約100語（パスフレーズ用）
   ローマ字表記で保存（出力はローマ字連結）
   ================================================== */
const HIRAGANA_WORDS = [
  // 自然
  'sakura','kaze','yuki','hoshi','umi','yama','hana','tsuki','sora','nami',
  'kawa','tori','ame','mori','niwa','michi','hikari','kumo','ishi','shima',
  'hama','oka','take','matsu','ume','kaede','kiri','shizen','asahi','yuuhi',
  // 感情・概念
  'yume','ai','kibou','chikara','kokoro','tamashii','heiwa','egao','inori',
  'yuuki','shiawase','imi','manabi','megumi','nozomi','omoide','chie','shinjitu',
  // 動き・状態
  'tobira','michishirube','nagare','hirameki','utagoe','omoi','kagayaki','nemuri',
  // 季節・時
  'haru','natsu','aki','fuyu','asa','yoru','hiru','ima','mukashi','mirai',
  'toki','hi','tsuki2','toshi',
  // 食物・もの
  'cha','sakana','kome','miso','yuzu','kaki','ichigo','ringo','nashi','momo',
  // 動物
  'usagi','neko','inu','kuma','taka','koi','kame','uma','shika','tsuru',
  // 色
  'aoi','akane','midori','shiro','kiniro','murasaki','kurenai',
  // 場所
  'machi','sato','kuni','furusato','minato','shiro2','tera','yashiro',
  // その他
  'kotoba','monogatari','densetsu','eien','majime','yasashisa',
];
