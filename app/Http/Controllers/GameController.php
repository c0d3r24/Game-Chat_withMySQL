<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Challenge;
use App\Game;
use App\GameMove;
use App\Result;

class GameController extends Controller
{
    private $message = [
        'positive' => 'yes',
        'negative' => 'no',
        'turnchanged' => 'Turn changed',
        'error' => 'Something went wrong',
        'waitingForOther' => 'Waiting for other player to move',
        'requestSend' => 'Your request has been sent',
        'canntSendRequest' => 'You cann\'t request',
        'invalidRequest' => 'Sorry! This is a invalid request',
        'notYourTurn' => 'Not your turn',
        'won' => 'You won the game',
        'invalidRequest' => 'You won the game',
        'needMore' => 'You need more',
        'waitingForDrag' => 'Waiting for you drag. Board with be updated in 10 sec and move will change unless you drag your piece',
        'gameOver' => 'Game Over!',
        'gameStart' => 'Game Started',
        'gameStartedError' => 'Game already started. Cann\'t make more entries.',
        'badRequest' => 'Bad request'
        ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){
        try {
            $testPlayer1 = filter_var($request->id, FILTER_SANITIZE_STRING);
            $gameId = filter_var($request->gameId, FILTER_SANITIZE_STRING);

            if ($testPlayer1 == '' || $gameId == '')
                return $this->sendResponse('false', $this->message['badRequest'], null, null);

            $testPlayer2 = auth()->user()->id;
            $data = ['messageData' => [], 'gameData' => []];
            $challengeStatus = null;

            // define snakes presence and how much it will take you down
            $snake = array(0 => 95, 1 => 98, 2 => 87, 3 => 54, 4 => 64, 5 => 17);
            $snakeByteDiff = [20, 20, 63, 20, 4, 10];

            // define ladders and how much clim to clim
            $ladder = array(0 => 4, 1 => 20, 2 => 28, 3 => 40, 4 => 63, 5 => 71);
            $ladderClimDiff = [10, 18, 56, 19, 18, 20];

            //roll the dice
            $diceValue = rand(1, 6);

            //retrieve game
            $gameMove = GameMove:: where('game_id', $gameId)->first();
            $originalPlayer1 = $gameMove->player0_id;
            $originalPlayer2 = $gameMove->player1_id;

            //for challenges
            $challenge = $this->returnChallenge($originalPlayer1, $originalPlayer2, 'neutral');
            // both players belong to this game
            if (
                ($originalPlayer1 == $testPlayer1 && $originalPlayer2 == $testPlayer2) ||
                ($originalPlayer1 == $testPlayer2 && $originalPlayer2 == $testPlayer1) ||
                $challenge->status !== 'accepted'
            ) {
                // decide which player
                ($testPlayer2 === (int)$originalPlayer1) ? $player = 'player0' : $player = 'player1';
                $opponentId = substr($player, -1, 1);
                ((int)$opponentId === 0) ? $opponentId = 'player1' : $opponentId = 'player0';

                // Check for valid turn.
                $challengeStatus = $challenge->status;

                // ($gameMove -> { $player . '_rolled'} === 'yes' && $gameMove -> { $opponentId . '_rolled'  === 'yes'})
                if (
                    $gameMove->whoseTurn !== (int)$gameMove->{$player . '_id'} ||
                    $gameMove->{$player . '_rolled'} !== 'no') { // this checks if the player who have played is a valid player.

                    $gameData = [
                        'game' => [
                            'id' => $gameMove->game_id,
                            'whoseTurn' => $gameMove->whoseTurn,
                            'diceValue' => $diceValue
                        ],
                        'player' => $this->returnPlayerArray($player, $gameMove, false)
                    ];
                    //$gameData = ['diceValue' => $diceValue, 'whoseTurn' => $gameMove -> whoseTurn , 'rolled' => $gameMove -> {$player . '_rolled'}  ];
                    $data['gameData'] = $gameData;
                    return $this->sendResponse(true, 'Not Your Turn', $data, $challengeStatus);
                } else { // if the user is a valid user to play

                    $oldScore = $gameMove->{$player . '_score'}; //get old score to generate new score...
                    $newScore = $diceValue + (int)$oldScore; // this will give new score.


                    $snakeIndex = array_search($newScore, $snake);
                    $ladderIndex = array_search($newScore, $ladder);



                    if ($snakeIndex !== false) {// test if this is a snake byte
                        $newScore = $newScore - $snakeByteDiff[$snakeIndex];
                    }

                    if ($ladderIndex !== false) { // test if this is a ladder climb
                        $newScore = $newScore + $ladderClimDiff[$ladderIndex];
                    }



                    if ($newScore > 99) {  //check if new dice value is making the more than 100;
                        $gameMove->{$player . '_rolled'} = 'yes';
                        $gameMove->{$opponentId . '_rolled'} = 'no';
                        $gameMove->whoseTurn = $gameMove->{$opponentId . '_id'};
                        $gameMove->save();
                        $winningDiff = 99 - $oldScore;
                        // $gameData = ['diceValue' => $diceValue, 'whoseTurn' => $gameMove -> whoseTurn , 'rolled' => $gameMove -> {$player . '_rolled'}  ];
                        $gameData = [
                            'game' => [
                                'id' => $gameMove->game_id,
                                'whoseTurn' => $gameMove->whoseTurn,
                                'diceValue' => $diceValue
                            ],
                            'player' => $this->returnPlayerArray($player, $gameMove, false)
                        ];
                        $data['gameData'] = $gameData;
                        return $this->sendResponse(true, 'You need more' . $winningDiff, $data, $challengeStatus);
                    } else { // this is an assurance that the move and score now can be saved in the data base.


                        // $originalDiff = $gameMove->{$player . '_difference'};
                        if ($gameMove->{$player . '_toPosition'} === '50,550' || $gameMove->{$player . '_toPosition'} === '150,550') {
                            // this check if the it is the first move
                            $gameMove->{$player . '_toPosition'} = '0,0';
                        }
                        $newDiff = intdiv($newScore, 10);
                        //$toPosition = explode(',', $gameMove->{$player . '_toPosition'});
                        $gameMove->{$player . '_fromPosition'} = $gameMove->{$player . '_toPosition'};

                        ($newScore % 10 === 0)
                            ? $gameMove->{$player . '_toPosition'} = ((int)$newDiff - 1) . ',9'
                            : $gameMove->{$player . '_toPosition'} = (int)$newDiff . ',' . (($newScore % 10) - 1);
                        $gameMove->{$player . '_score'} = $newScore;
                        $gameMove->{$player . '_diceValue'} = $diceValue;
                        $gameMove->{$player . '_rolled'} = 'yes';

                        $gameMove->save();


                        if ((int)$gameMove->{$player . '_score'} === 99) {
                            $challengeStatus = $challenge->status;
                            $result = new Result();
                            $result->game_id = $gameId;
                            $result->winner = $gameMove->{$player . '_id'};
                            $result->loser = $gameMove->{$opponentId . '_id'};
                            $result->save();
                            $returnResult = ['gameId' => $result->game_id, 'winner' => $result->winner, 'loser' => $result->loser];
                            $gameData = [
                                'game' => [
                                    'id' => $gameMove->game_id,
                                    'whoseTurn' => $gameMove->whoseTurn,
                                    'diceValue' => $diceValue
                                ],
                                'result' => $returnResult
                            ];
                            $data['gameData'] = $gameData;
                            return $this->sendResponse(true, $this->message['won'], $data, $challengeStatus);
                        }


                        $gameData = [
                            'game' => [
                                'id' => $gameMove->game_id,
                                'whoseTurn' => $gameMove->whoseTurn,
                                'diceValue' => $diceValue
                            ],
                            'player' => $this->returnPlayerArray($player, $gameMove, false)
                        ];
                        $data['gameData'] = $gameData;
                        return $this->sendResponse(
                            true,
                            'Waiting for you drag. Board with be updated in 10 sec and move will change unless you drag your piece',
                            $data,
                            $challengeStatus
                        );
                    }
                }
            } else {
                return $this->sendResponse(true, 'Sorry! This is a invalid request', $data, $challengeStatus);
            }
        } catch (\Illuminate\Database\QueryException $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        } catch(Exception $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        }
    }


    /**
     * Change turn and update board for the players.
     */
    public function changeTurn(Request $request){

    try {
        $testPlayer1 = filter_var($request->id, FILTER_SANITIZE_STRING);
        $gameId = filter_var($request->gameId, FILTER_SANITIZE_STRING);

        if ($testPlayer1 == '' || $gameId == '')
            return $this->sendResponse('false', $this->message['badRequest'], null, null);

        $testPlayer2 = auth()->user()->id;
        $data = ['messageData' => [], 'gameData' => []];
        $challengeStatus = null;

        //retrieve game
        $gameMove = GameMove:: where('game_id', $gameId)->first();
        $originalPlayer1 = $gameMove->player0_id;
        $originalPlayer2 = $gameMove->player1_id;

        //for challenges
        $challenge = $this->returnChallenge($originalPlayer1, $originalPlayer2, 'neutral');
        $challengeStatus = $challenge->status;

        if (
            ($originalPlayer1 == $testPlayer1 && $originalPlayer2 == $testPlayer2) ||
            ($originalPlayer1 == $testPlayer2 && $originalPlayer2 == $testPlayer1) ||
            $challengeStatus !== 'accepted'
        ) {
            // decide which player
            ($testPlayer2 === (int)$originalPlayer1) ? $player = 'player0' : $player = 'player1';
            $opponentId = substr($player, -1, 1);
            ((int)$opponentId === 0) ? $opponentId = 'player1' : $opponentId = 'player0';
            // Check for valid turn.

            if ($gameMove->whoseTurn == $gameMove->{$opponentId . '_id'}) {
                return $this->sendResponse(
                    true,
                    $this->message['waitingForOther'],
                    $data,
                    $challengeStatus
                );
            } else {
                $gameMove->{$opponentId . '_rolled'} = 'no';
                $gameMove->whoseTurn = $gameMove->{$opponentId . '_id'};
                $gameMove->save();
                $gameData = [
                    'game' => [
                        'id' => $gameMove->game_id,
                        'whoseTurn' => $gameMove->whoseTurn,
                    ],
                    'player' => $this->returnPlayerArray($player, $gameMove, false)
                ];
                $data['gameData'] = $gameData;
                return $this->sendResponse(
                    true,
                    $this->message['turnchanged'],
                    $data,
                    $challengeStatus
                );
            }
        }
    } catch (\Illuminate\Database\QueryException $ex) {
        return $this -> sendResponse(false, $this -> message['error'], null, null);
        } catch(Exception $ex) {
        return $this -> sendResponse(false, $this -> message['error'], null, null);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $gameMove = GameMove:: where('game_id', $id)->first();
            $originalPlayer1 = $gameMove->player0_id;
            $originalPlayer2 = $gameMove->player1_id;
            $challenge = $this->returnChallenge($originalPlayer1, $originalPlayer2, 'neutral');
            $challengeStatus = $challenge->status;
            (auth()->user()->id == $originalPlayer1) ? $thisPlayer = 'player0' : $thisPlayer = 'player1';
            ($thisPlayer == 'player0') ? $opponent = 'player1' : $opponent = 'player0';

            if ($challengeStatus === 'finished') {
                $result = Result::where('game_id', $id)->first();
                $returnResult = ['gameId' => $result->game_id, 'winner' => $result->winner, 'loser' => $result->loser];
                $gameData = [
                    'game' => [
                        'id' => $gameMove->game_id,
                        'whoseTurn' => $gameMove->whoseTurn,
                        'diceValue' => 0
                    ],
                    'result' => $returnResult
                ];
                $data['gameData'] = $gameData;
                return $this->sendResponse(
                    true,
                    $this->message['gameOver'],
                    $data,
                    $challengeStatus
                );
            }
            if ($gameMove->{$opponent . '_rolled'} == 'yes' && $gameMove->{$thisPlayer . '_rolled'} == 'no') {
                $data = ['messageData' => [], 'gameData' => []];
                $gameData = [
                    'game' => [
                        'id' => $gameMove->game_id,
                        'whoseTurn' => $gameMove->whoseTurn,
                    ],
                    'opponent' => $this->returnPlayerArray($opponent, $gameMove, false),
                    'thisPlayer' => $this->returnPlayerArray($thisPlayer, $gameMove, false)
                ];
                $data['gameData'] = $gameData;
                return $this->sendResponse(true, 'Retrieved successfully', $data, $challengeStatus);
            } else {
                $data = ['messageData' => [], 'gameData' => []];
                $gameData = [
                    'game' => [
                        'id' => $gameMove->game_id,
                        'whoseTurn' => $gameMove->whoseTurn,
                    ]
                ];
                $data['gameData'] = $gameData;
                return $this->sendResponse(true, 'Waiting for other player', $data, $challengeStatus);
            }
        } catch (\Illuminate\Database\QueryException $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        } catch(Exception $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        }
}



    /**
     * @param $id id of the opponent
     * @return \Illuminate\Http\Response
     */
    public function invite($id){

           try {
               $user = auth()->user()->id;
               $data = ['messageData' => [], 'gameData' => []];
               $challengeStatus = null;

               $challenge = $this->returnChallenge($user, $id, 'neutral');

               if ($challenge === null || $challenge->status === 'finished') {
                   $challenge = new Challenge();
                   $receiver_id = $id;
                   $sender_id = auth()->user()->id;
                   $message_id = $sender_id . '|' . $receiver_id;
                   $challenge->id = $message_id;
                   $challenge->sender = $sender_id;
                   $challenge->active = true;
                   $challenge->status = 'requested';
                   $challenge->receiver = $receiver_id;
                   $challenge->save();
                   $challengeStatus = 'requested';
                   return $this->sendResponse(
                       true,
                       $this->message['requestSend'],
                       $data,
                       $challengeStatus
                   );
               } else {
                   return $this->sendResponse(
                       true,
                       $this->message ['canntSendRequest'],
                       $data,
                       $challengeStatus
                   );
               }
           } catch (\Illuminate\Database\QueryException $ex) {
               return $this -> sendResponse(false, $this -> message['error'], null, null);
           } catch(Exception $ex) {
               return $this -> sendResponse(false, $this -> message['error'], null, null);
           }
    }

    /**
     * @param $id id of the opponent
     * @return \Illuminate\Http\Response
     */
    public function accept($id){
        try {
            $SIZE = 50;
            $HEIGHT = 10;
            $WIDTH = 10;
            $challengeStatus = null;
            $data = ['messageData' => [], 'gameData' => []];
            if ($id !== '') {
                $user = auth()->user()->id;
                $challenge = $this->returnChallenge($user, $id, 'requested');
                $challengeStatus = $challenge->status;
                if ($challenge !== null && $challenge->game_id === null) {
                    $game = new Game();
                    $game->player1 = $id;
                    $game->player2 = $user;
                    $game->who_start = $id;
                    $game->board_dimension = $HEIGHT . 'x' . $WIDTH . 'x' . $SIZE;
                    $game->save();

                    // update challenge table
                    $challenge->status = 'accepted';
                    $challenge->game_id = $game->id;
                    $challenge->save();

                    // create new game.
                    $move = new GameMove();
                    $move->game_id = $game->id;
                    $move->whoseTurn = $id;


                    // assign new values to player 1
                    $move->player0_id = $id;
                    $move->player0_pieceId = $id;
                    $move->player0_diceValue = 0;
                    $move->player0_fromPosition = '0,0';
                    $move->player0_toPosition = '50,550';
                    $move->player0_moveType = 'initial';
                    $move->player0_score = 0;
                    $move->player0_difference = 0;
                    $move->player0_rolled = 'no';

                    // assign new values to player 2
                    $move->player1_id = $user;
                    $move->player1_pieceId = $user;
                    $move->player1_diceValue = 0;
                    $move->player1_fromPosition = '0,0';
                    $move->player1_toPosition = '150,550';
                    $move->player1_moveType = 'initial';
                    $move->player1_score = 0;
                    $move->player1_difference = 0;
                    $move->player1_rolled = 'yes';
                    $move->save();

                    /********************************************************************************************************************************/
                    // board size
                    list($HEIGHT, $WIDTH, $SIZE) = explode('x', $challenge->game->board_dimension);
                    $gameData = [
                        'game' => [
                            'id' => $move->game_id,
                            'whoseturn' => $move->whoseTurn,
                            'height' => $HEIGHT,
                            'width' => $WIDTH,
                            'size' => $SIZE
                        ],
                        'player0' => $this->returnPlayerArray('player0', $move, false),
                        'player1' => $this->returnPlayerArray('player1', $move, false)
                    ];
                    /********************************************************************************************************************************/

                    $data['gameData'] = $gameData;
                    return $this->sendResponse(
                        true,
                        $this->message['gameStart'],
                        $data,
                        $challengeStatus
                    );
                }

                return $this->sendResponse(
                    true,
                    $this->message['gameStartedError'],
                    $data,
                    $challengeStatus
                );
            }
        } catch (\Illuminate\Database\QueryException $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        } catch(Exception $ex) {
            return $this -> sendResponse(false, $this -> message['error'], null, null);
        }
    }


    /**
     *
     */
    public function getChallenges($id){
        if($id !== ''){
            $user = auth()->user()->id;
            $challenge = $this -> returnChallenge($user, $id, 'requested');
            $data = ['challenge' => $challenge];
            return array('status'=> true, 'data' => $data);
        }
    }

    public function getBoardData($id) {
        $user = auth()->user()->id;
        $searchParam1 = $id . '|' . $user;
        $searchParam2 = $user . '|' . $id;
        $challenge = Challenge::where('id', $searchParam1)->orWhere('id', $searchParam2)
            ->first();
        $dimension = explode('x', $challenge->game->board_dimension);
        $gameData = ['height' => $dimension[0], 'width' => $dimension[1], 'size' => $dimension[2], 'who_start' => $challenge->game->who_start,
            'gameID' => $challenge->game_id, 'positionX' => 0, 'positionY' => 0];
        $data = ['status' => $challenge->status, 'message' => 'Game started', 'gameData' => $gameData];
        return array('status' => true, 'data' => $data);
    }

    /******************************************************************************************************************************************/
    /*********************************************** helper function to return consistent response ********************************************/

    public function sendResponse($status, $message, $data, $challengeStatus){
        return array(
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'challengeStatus' => $challengeStatus
        );
    }
    /******************************************************************************************************************************************/


    /******************************************************************************************************************************************/
    /***************************************************** helper function to return challenge status *****************************************/

    public function returnChallenge($player0, $player2, $condition){
        //for challenges
        $searchParam1  = $player0 . '|' . $player2;
        $searchParam2  = $player2 . '|' . $player0;
        switch($condition){
            case 'neutral':
                return  Challenge::where('id',$searchParam1)
                    ->orWhere('id',$searchParam2)
                    ->orderBy('created_at','desc')
                    ->first();
            case 'requested':
                return  Challenge::where('id', $searchParam1)->orWhere('id', $searchParam2)->where('status','requested')
                    ->orderBy('created_at','desc')
                    ->first();
        }

    }
    /******************************************************************************************************************************************/

    /******************************************************************************************************************************************/
    /*********************************************** helper function to return player's moves data ********************************************/

    public function returnPlayerArray($player, $gameMove, $rolled) {

        ($rolled)
            ? list($X, $Y) = explode(',', $gameMove -> { $player . '_fromPosition' } )
            : list($X, $Y) = explode(',', $gameMove -> { $player . '_toPosition' } );


        $positionX = $X;
        $positionY =  $Y;
        $playerScore = $gameMove->{ $player . '_score' };
        $playerRolled = $gameMove->{ $player . '_rolled' };

        return [
            'id' =>  $gameMove -> { $player . '_id' },
            'piece_id' => $gameMove -> { $player . '_pieceId' },
            'x' => $positionX,
            'y' => $positionY,
            'score' => $playerScore,
            'rolled' => $playerRolled
        ];
    }


    /******************************************************************************************************************************************/
    /** ************************************************ Unused paths/functions *****************************************************************/

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id){
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id){
        //
    }
}
