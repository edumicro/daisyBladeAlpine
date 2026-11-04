// Bridge for the PHP tests (tests/Feature/LaravelAnswerShapesTest.php): reads an answer that REAL Laravel classes produced
// (the JSON on stdin) with `answerShapes[shape]` and prints what the component would receive, or the reason it would refuse it.
//
//     echo '{"data":[...]}' | node tests/js/helpers/read-answer.mjs tableRows
import { answerShapes, isContractError } from '../../../resources/js/http.js'

const [shape] = process.argv.slice(2)
let json = ''
for await (const chunk of process.stdin) json += chunk

console.error = () => {}   // the warning for the developer is not what is being tested
try {
    console.log(JSON.stringify({ result: answerShapes[shape](JSON.parse(json), '/endpoint') }))
} catch (error) {
    console.log(JSON.stringify({ error: isContractError(error) ? error.reason : String(error) }))
}
