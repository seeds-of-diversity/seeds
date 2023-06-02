<html>
<head>
<style>
.treasure_heading     { width:100%; height:60px; padding:5px;background-color:#6af; color:white }
.treasure_head_nPage  { font-size:12pt }
.treasure_head_title  { font-size:16pt; font-weight:bold }

</style>

<script>
var oHunt = [
    { title: "Colour Choice",
      question: "What is your favourite colour?",
      answer: "green",
      lesson: "Green is good"
    },
    { title: "Shape Choice",
      question: "What is your favourite shape?",
      answer: "circle",
      lesson: "Circle is good"
    },
    { title: "Food Preference",
      question: "What is your favourite food?",
      answer: "beans",
      lesson: "Beans are good"
       
    },
];

var nPage = 0;



function main()
{
    nPage = 0;
    drawPage(false);
}

function drawPage( bGuess )
{
    let s = "";
    let bCorrect = false;
    let sResult = "";

    if(bGuess) {
        let v = document.getElementById('treasure_guess').value;
        bCorrect = (v == oHunt[nPage].answer);
        
        sResult = `You guessed ${v} : `;
        if(bCorrect) {
            sResult += `You got it!`;
        } else {
            sResult += `No, try again`;
        } 
    }
        
    s += `<div class='treasure_heading'>
            <div class='treasure_head_nPage'>${nPage+1}/10</div>
            <div class='treasure_head_title'> ${oHunt[nPage].title}</div>
          </div>
          <div class='treasure_q'>
            <div class='treasure_q_question'>${oHunt[nPage].question}
                <br/><input type='text' id='treasure_guess'/></div>
            <div class='treasure_q_result'>${sResult}</div>
          </div>`;
    
    if( bCorrect ) {
        s += `<div class='treasure_lesson'>${oHunt[nPage].lesson}</div>`;
    }

    s += `<div><button id='treasure_button_next' class='treasure_button treasure_button_next'>Next</button></div>`;    
    
    document.getElementById('treasure_body').innerHTML = s;
    
    document.getElementById("treasure_button_next").addEventListener("click", bCorrect ? clickedNext : clickedGuess);
    
    return(s);
}

function clickedGuess(e)
{
    
    drawPage(true);
}

function clickedNext(e)
{
    nPage++;
    drawPage(false);
}
</script>
</head>
<body onload='main()'>
<div id='treasure_body'></div>
</body>
</html>