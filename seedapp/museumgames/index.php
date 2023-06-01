<html>
<head>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
<script
  src="https://code.jquery.com/jquery-3.7.0.min.js"
  integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g="
  crossorigin="anonymous"></script>

<style>
.treasure_heading     { width:100%; padding:5px;background-color: #0067b1; color:white }
.treasure_head_nPage  { font-size:30px }
.treasure_head_title  { font-size:30pt; font-weight:bold }

.MG_header {
    background-color: #0067b1;
    color: white;
    padding: 30px;
}

.MG_startpage {
    background-color: #0067b1;
    color: white;
}

.MG_startpage p {
    font-size: 24px;
}

.MG_startpage h2 {
    font-size: 36px;
}

.SoDHomeBlock {

}
.SoDHomeBlock01 {

}
.SoDHomeBlock02 {

}
.SoDHomeBlock .SoDHomeBlockImg {
    position:relative;
    overflow:hidden;
    padding-bottom:80%;
    background-position: center center;
    background-repeat: no-repeat;
    background-size:cover;
    margin:10px 0px;
}
.SoDHomeBlock .SoDHomeBlockCaption {
    position:absolute;
    top:10%;
    left:10%;
    right:50%;

    margin:0px auto 15px auto;
    padding:20px;

    color: #222;

    background-color: white;
    opacity: 85%;
    border-radius:20px;

    font-size: 24px;
}
.SoDHomeBlock .SoDHomeBlockCaption2 {
    position:absolute;
    top:10%;
    left:60%;
    right:10%;

    margin:0px auto 15px auto;
    padding:20px;

    color: #222;

    background-color: white;
    opacity: 85%;
    border-radius:20px;

    font-size: 24px;
}

.SoDHomeBlock .MGBlock_Nope {
    position:absolute;
    top:10%;
    left:60%;
    right:10%;

    margin:0px auto 15px auto;
    padding:20px;

    color: #222;

    background-color: white;
    opacity: 85%;
    border-radius:20px;

    display:none;
}
.MGBlock_Lesson {
    display: none;
}



/* Screen < sm : all blocks are full width */
.SoDHomeBlock01 .SoDHomeBlockCaption h4 { font-size:xx-large; font-weight:bold; }
.SoDHomeBlock01 .SoDHomeBlockCaption    { font-size:24px;   font-weight:bold; }
.SoDHomeBlock02 .SoDHomeBlockCaption h4 { font-size:xx-large;   font-weight:bold; }
.SoDHomeBlock02 .SoDHomeBlockCaption    { font-size:x-large;  font-weight:bold; }

/* Screen > sm: block01 is four times bigger than block02 */
@media only screen and (min-width : 768px){
.SoDHomeBlock01 .SoDHomeBlockCaption h4 { font-size:x-large; font-weight:bold; }
.SoDHomeBlock01 .SoDHomeBlockCaption    { font-size:24px;   font-weight:bold; }
.SoDHomeBlock02 .SoDHomeBlockCaption h4 { font-size:large;   font-weight:bold; }
.SoDHomeBlock02 .SoDHomeBlockCaption    { font-size:medium;  font-weight:bold; }
}

</style>

<script>
var oHunt = [
    { title: "When was the train engine made?",
      question: "Search the train engine for a plate that shows when it was built",
      q_choices: ["1895", "1911", "1921"],
      answer: 2,
      lesson: "<p>This steam locomotive was built in Montreal in January 1911. Steam power was commonly used for the first half of the 20th century. In the late 1940s railways began to use diesel-electric locomotives for main-line freight and passenger service. By 1960 both CN and CP railways had stopped using steam locomotives in regularly scheduled trains.",
      imgA: "https://seedliving.ca/museumgames/i/1a.jpg",
      imgB: "https://seedliving.ca/museumgames/i/1b.jpg",
    },
    { title: "How far is it to Toronto?",
      question: "Find the distance between Petersburg and Toronto stations.",
      q_choices: ["51.55 miles", "73.4 miles", "62.82 miles", "102.3 miles"],
      answer: 3,
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

        bCorrect = bGuess ? isCorrect(iChoice) : false;

        if(bGuess && iChoice) {
            let v = oHunt[nPage-1].q_choices[iChoice-1];

            sResult = `You guessed ${v} : `;
            if( bCorrect ) {
                sResult += `You got it!`;
            } else {
                sResult += `No, try again`;
            }
        }

        let sQuestion = "<p>"+oHunt[nPage-1].question+"</p>";
        for(let i = 0; i < oHunt[nPage-1].q_choices.length; i++) {
            sQuestion += `<input type='radio' value='${i+1}' id='choice${i+1}'/>&nbsp;&nbsp;<label for='choice${i+1}'>${oHunt[nPage-1].q_choices[i]}</label><br/><br/>`;
        }

cLesson = nPage==1 ? "SoDHomeBlockCaption" : "SoDHomeBlockCaption2";

        s += `<div class='treasure_heading'>
                <div class='treasure_head_nPage'>${nPage}/2</div>
                <div class='treasure_head_title'> ${oHunt[nPage-1].title}</div>
              </div>
              <div class='SoDHomeBlock SoDHomeBlock01'>
                <div class='SoDHomeBlockImg' style='background-image: url("${oHunt[nPage-1].imgA}");'>
                  <div class='SoDHomeBlockCaption'>
                    <div class='treasure_q_question'>${sQuestion}</div>
                    <div class='treasure_q_result'>${sResult}</div>
                  </div>
                  <!--
                  <div class='MGBlock_Nope'>
                    <div class=''>${sResult}</div>
                  </div>
                  -->
                </div>
              </div>
              <div class='SoDHomeBlock MGBlock_Lesson'>
                <div class='SoDHomeBlockImg' style='background-image: url("${oHunt[nPage-1].imgB}");'>
                  <div class='${cLesson}'>
                      <div><h2>You got it!</h2> ${oHunt[nPage-1].lesson}</div>
                      <div><button id='treasure_button_next' class='treasure_button treasure_button_next'
                            style='font-size:24px;padding:1em;border-radius:1em'>&nbsp;&nbsp;Next&nbsp;&nbsp;</button></div>
                  </div>
                </div>
              </div>
<!--
              <div class='treasure_q' style='width:100%;background-image:url("./i/1a.jpg");background-repeat:no-repeat;background-size:cover'>
                <div class='treasure_q_question'>${oHunt[nPage-1].question}
                    <br/><input type='text' id='treasure_guess'/></div>
                <div class='treasure_q_result'>${sResult}</div>
              </div>
-->
              `;


//        if( bCorrect ) {
//            s += `<div class='treasure_lesson'>${oHunt[nPage].lesson}</div>`;
//        }


        document.getElementById('treasure_body').innerHTML = s;
        document.getElementById("treasure_button_next").addEventListener("click", bCorrect ? clickedNext : clickedGuess);

        $("input[type='radio']").click( clickedGuess );
    }
}

function clickedGuess(e)
{
    let iChoice = $(this).val();

    drawPage(true, iChoice);

    $(".MGBlock_Nope").show();

    if( isCorrect(iChoice) ) {
        $(".MGBlock_Lesson").show();
        $(".MGBlock_Lesson")[0].scrollIntoView();
    }
}

function isCorrect( iChoice ) { return( iChoice == oHunt[nPage-1].answer ); }

function clickedNext(e)
{
    nPage++;
    drawPage(false);
    window.scrollTo(0,0);
}

function drawStartPage()
{
    s = `<div class='MG_startpage'>
           <div class='MG_header'>
             <div style='max-width:360px'><img src='https://seedliving.ca/museumgames/i/region-of-waterloo-museums.svg'/></div>
           </div>
           <div style='margin:30px'>
             <h2>Welcome to our Scavenger Hunt!</h2>
             <div style='margin: 30px 3em'>
               <p><strong>Doon Heritage Village</strong></p>
               <p>Have fun searching the Train Station and Martin House for the answers to our scavenger hunt questions.</p>
               <button class='' id='btnStart' style='font-size:24px;padding:1em;border-radius:1em'>Click here to get started</button>
           </div>
           <p>&nbsp;</p>
         </div>`;
    document.getElementById('treasure_body').innerHTML = s;
    document.getElementById("btnStart").addEventListener("click", clickedNext);
}

function drawEndPage()
{
    s = `<div class='MG_startpage'>
           <div class='MG_header'>
             <div style='max-width:360px'><img src='https://seedliving.ca/museumgames/i/region-of-waterloo-museums.svg'/></div>
           </div>
           <div style='margin:30px'>
             <h2>Thanks for playing our Scavenger Hunt!</h2>
             <div style='margin: 30px 3em'>
               <p><strong>We hope you have a great day visiting the Ken Seiling Waterloo Region Museum</strong></p>
           </div>
           <p>&nbsp;</p>
         </div>`;
    document.getElementById('treasure_body').innerHTML = s;
}

</script>
</head>
<body onload='main()'>
<div id='treasure_body'></div>
</body>
</html>