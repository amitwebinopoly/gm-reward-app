Intl.PluralRules&&typeof Intl.PluralRules.__addLocaleData=="function"&&Intl.PluralRules.__addLocaleData({data:{categories:{cardinal:["one","many","other"],ordinal:["many","other"]},fn:function(a,o){var t=String(a),l=t.split(/[ce]/),e=l[1]||0,r=String(e?Number(l[0])*Math.pow(10,e):t).split("."),n=r[0],i=!r[1],u=n.slice(-6);return o?a==11||a==8||a==80||a==800?"many":"other":a==1&&i?"one":e==0&&n!=0&&u==0&&i||e<0||e>5?"many":"other"}},locale:"it"});