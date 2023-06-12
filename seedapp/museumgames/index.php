<html>
<head>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
<script
  src="https://code.jquery.com/jquery-3.7.0.min.js"
  integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g="
  crossorigin="anonymous"></script>

<style>

/* MGStart_page             is the top-level div for the starting page
   MGStart_head             is the upper section of the starting page where the logo is shown
   MGStart_main             is the main section of the starting page

   MGGame_page              is the top-level div for the pages where the game happens
   MGGame_head              is the header of the game page
   MGGame_head_progress     shows the current progress
   MGGame_head_title        shows the title of the current game step

   MGGame_sect1             is the first section of the game page
   MGGame_sect2             is the second section of the game page (revealed on a correct answer)
   MGGame_sectImgContainer  defines a container for the image to fill
   MGBlock_text             is a semi-opaque text block
   MGBlock_left             is a semi-opaque text block on the left half of the containing sect
   MGBlock_right            is a semi-opaque text block on the right half of the containing sect
*/

.MGStart_page {
    background-color: #0067b1;
    color: white;
}
.MGStart_page p {
    font-size: 24px;
}
.MGStart_page h2 {
    font-size: 36px;
}
.MGStart_head {
    background-color: #0067b1;
    color: white;
    padding: 30px;
}
.MGStart_main {
    background-color: #0067b1;
    color: white;
    padding: 30px;
}

.MGGame_page {}
.MGGame_head           { width:100%; padding:5px;background-color: #0067b1; color:white }
.MGGame_head_progress  { font-size:30px }
.MGGame_head_title     { font-size:30pt; font-weight:bold }

.MGGame_sect2 {
    display: none;
}

.MG_button1 {
    font-size:24px; padding:1em; border-radius:1em;
}
/* MGGame_question is a text question inside a Block
 * MGGame_q_choice is a multiple choice line within the MGGame_question
 * MGGame_q_result is a message below the question, typically saying "try again"
 */
.MGGame_question {}
.MGGame_q_choice { margin-top:1em; }
.MGGame_q_result { margin-top:1em; }


.MGGame_sectImgContainer {
    position:relative;
    overflow:hidden;
    padding-bottom: 80%;
    background-position: center center;
    background-repeat: no-repeat;
    background-size:cover;
    margin:10px 0px;
}
.MGBlock_text {
    margin:0px auto 15px auto;
    padding:20px;
    color: #222;
    background-color: white;
    opacity: 85%;
    border-radius:20px;
    font-size: 24px;
}
.MGBlock_left {
    position:absolute;
    top:10%;
    left:10%;
    right:50%;
}
.MGBlock_right {
    position:absolute;
    top:10%;
    left:60%;
    right:10%;
}

</style>

<script>
var oHunt = [
    { title: "When was the train engine made?",
      question: "Search the train engine for a plate that shows when it was built",
      q_choices: ["1895", "1911", "1921"],
      answer: 2,
      posQ: 'L',
      posA: 'L',
      lesson: "<p>This steam locomotive was built in Montreal in January 1911. Steam power was commonly used for the first half of the 20th century. In the late 1940s railways began to use diesel-electric locomotives for main-line freight and passenger service. By 1960 both CN and CP railways had stopped using steam locomotives in regularly scheduled trains.",
      imgA: "https://seedliving.ca/museumgames/i/1a.jpg",
      imgB: "https://seedliving.ca/museumgames/i/1b.jpg",
    },
    { title: "How far is it to Toronto?",
      question: "Find the distance between Petersburg and Toronto stations.",
      q_choices: ["51.55 miles", "73.4 miles", "68.82 miles", "102.3 miles"],
      answer: 3,
      posQ: 'L',
      posA: 'R',
      lesson: "<p>Before cars and highways were common, passenger trains moved the majority of people from city to city for business and pleasure, Reliable, frequent service meant that you could get just about anywhere in Southern Ontario and back in the same day.</p>",
      imgA: "https://seedliving.ca/museumgames/i/2a.jpg",
      imgB: "https://seedliving.ca/museumgames/i/2b.jpg",
    }
];

var nPage = 0;



function main()
{
    nPage = 0;
    drawPage(false);
}

function drawPage( bGuess, iChoice = 0 )
{
    let s = "";
    let sResult = "";

    if( !nPage ) {
        drawStartPage();
    } else if( nPage > 2 ) {
        drawEndPage();
    } else {
        let oP = oHunt[nPage-1];

        bCorrect = bGuess ? isCorrect(iChoice) : false;

        if(bGuess && iChoice) {
            let v = oP.q_choices[iChoice-1];

            sResult = `You guessed ${v} : `;
            if( bCorrect ) {
                sResult += `You got it!`;
            } else {
                sResult += `No, try again`;
            }
        }

        let sQuestion = `<h2>${oP.question}</h2>`;
        let cQ      = oP.posQ=='R' ? "MGBlock_right" : "MGBlock_left";
        let cLesson = oP.posA=='R' ? "MGBlock_right" : "MGBlock_left";
        for(let i = 0; i < oP.q_choices.length; i++) {
            sQuestion += `<div class='MGGame_q_choice'><input type='radio' value='${i+1}' id='choice${i+1}'/>&nbsp;&nbsp;<label for='choice${i+1}'>${oP.q_choices[i]}</label></div>`;
        }

        s +=
          `<div class='MGGame_page'>
             <div class='MGGame_head'>
               <div class='MGGame_head_progress'>${nPage}/2</div>
               <div class='MGGame_head_title'> ${oP.title}</div>
             </div>
             <div class='MGGame_sect1'>
               <div class='MGGame_sectImgContainer' style='background-image: url("${oP.imgA}");'>
                 <div class='${cQ} MGBlock_text' style='font-weight:bold'>
                   <div class='MGGame_question'>${sQuestion}</div>
                   <div class='MGGame_q_result'>${sResult}</div>
                 </div>
               </div>
             </div>
             <div class='MGGame_sect2'>
               <div class='MGGame_sectImgContainer' style='background-image: url("${oP.imgB}");'>
                 <div class='${cLesson} MGBlock_text'>
                   <div><h2>You got it!</h2> ${oP.lesson}</div>
                   <div><button id='MG_button_next' class='MG_button1'>&nbsp;&nbsp;Next&nbsp;&nbsp;</button></div>
                 </div>
               </div>
             </div>`;

        document.getElementById('MG_Page_Body').innerHTML = s;
        document.getElementById("MG_button_next").addEventListener("click", bCorrect ? clickedNext : clickedGuess);

        $("input[type='radio']").click( clickedGuess );
    }
}

function clickedGuess(e)
{
    let iChoice = $(this).val();

    drawPage(true, iChoice);

    if( isCorrect(iChoice) ) {
        $(".MGGame_sect2").show();
        $(".MGGame_sect2")[0].scrollIntoView();
    }
}

function isCorrect( iChoice ) { return( iChoice == oHunt[nPage-1].answer ); }

function clickedNext(e)
{
    nPage++;
    window.scrollTo(0,0);
    drawPage(false);
}

function drawStartPage()
{
    s = `<div class='MGStart_page'>
           <div class='MGStart_head'>
             <div style='max-width:360px'><img src='https://seedliving.ca/museumgames/i/region-of-waterloo-museums.svg'/></div>
           </div>
           <div class='MGStart_main'>
             <h2>Welcome to our Scavenger Hunt!</h2>
             <div style='margin: 30px 3em'>
               <p><strong>Doon Heritage Village</strong></p>
               <p>Have fun searching the Train Station and Martin House for the answers to our scavenger hunt questions.</p>
               <button class='MG_button1' id='btnStart'>Click here to get started</button>
           </div>
           <p>&nbsp;</p>
         </div>`;
    document.getElementById('MG_Page_Body').innerHTML = s;
    document.getElementById("btnStart").addEventListener("click", clickedNext);
}

function drawEndPage()
{
    // using MGPage_start for end page because they look the same
    s = `<div class='MGStart_page'>
           <div class='MGStart_head'>
             <div style='max-width:360px'><img src='https://seedliving.ca/museumgames/i/region-of-waterloo-museums.svg'/></div>
           </div>
           <div class='MGStart_main'>
             <h2>Thanks for playing our Scavenger Hunt!</h2>
             <div style='margin: 30px 3em'>
               <p><strong>We hope you have a great day visiting the Ken Seiling Waterloo Region Museum.</strong></p>
           </div>
           <p>&nbsp;</p>
         </div>`;
    document.getElementById('MG_Page_Body').innerHTML = s;
}

</script>
</head>
<body onload='main()'>
<div id='MG_Page_Body'></div>
</body>
</html>