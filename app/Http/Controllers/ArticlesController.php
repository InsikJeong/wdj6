<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ArticlesController extends Controller
{
    public function __construct()
    {
        //로그인 하지 않아도 index(게시판 홈), show(게시판 뷰)는 볼 수 있음
        $this->middleware('auth', ['except' => ['index', 'show', ]]);
    }
    //메인 게시판
    public function index($slug = null)
    {
        \Log::info($slug);
        //$slug는 태그로 검색할 경우에만 존재함
        $query = $slug ? \App\Tag::whereSlug($slug)->first()->articles() : new \App\Article;
        $articles = $query->orderBy('id','desc')->paginate(5); #게시글 목록(위의 query조건에 맞는)
        $article = new \App\Article;        //게시글 전체
        \Log::info($articles);
        return view('articles.index', compact('article', 'articles'));
    }

    //게시판 작성 완료
    //ArticlesRequest안에 제목, 내용에 대한 rule이 있음
    public function store(\App\Http\Requests\ArticlesRequest $request)
    {   
        $article = $request->user()->articles()->create($request->all());
        //$article는 auth()->user()->articles()->create()를 호출함
        //==> 로그인한 유저의 게시판을 작성
        if (!$article) {
            flash()->error('글 작성 실패');
            return back()->withInput();
        }
        $article->tags()->sync($request->input('tags'));
        if ($request->hasFile('files')) {
           // 파일 저장
           $files = $request->file('files');
           foreach($files as $file) {
               $filename = Str::random().filter_var($file->getClientOriginalName(), FILTER_SANITIZE_URL);
               // 순서 중요 !!!
               // 파일이 PHP의 임시 저장소에 있을 때만 getSize, getClientMimeType등이 동작하므로,
               // 우리 프로젝트의 파일 저장소로 업로드를 옮기기 전에 필요한 값을 취해야 함.
               $article->attachments()->create([
                   'filename' => $filename,
                   'bytes' => $file->getSize(),
                   'mime' => $file->getClientMimeType()
               ]);
               $file->move(attachments_path(), $filename);
           }
        }
        flash()->success('게시판을 생성하였습니다.');
        //이벤트를 발생시킴 
        // var_dump('이벤트 발생완료');
        return response()->json([], 204);
    }
    //게시판 뷰
    public function show(\App\Article $article)           //$id = URL에서 넘겨지는 리소스아이디
    {
        // debug($article->toArray());
        $comments = $article->comments()->with('replies')->whereNull('parent_id')->latest()->get();
        return view('articles.show', compact('article','comments'));
    }
    //게시판 수정 요청
    public function edit(\App\Article $article)
    {
        $this->authorize('update', $article);
        // flash()->success('수정하신 내용을 저장했습니다.');
        return view('articles.edit', compact('article'));
    }
    //게시판 수정 완료
    public function update(\App\Http\Requests\ArticlesRequest $request, \App\Article $article)
    {   
        $this->authorize('update', $article);
        $article->update($request->all());

        \Log::info($article->attachments);

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            foreach($files as $file) {
                $filename = Str::random().filter_var($file->getClientOriginalName(), FILTER_SANITIZE_URL);
                
                //수정하기전 게시글에 첨부파일에 존재할 경우    -> 첨부파일을 수정
                if ($article->attachments()->exists()) {
                    \Log::info("수정");
                    $article->attachments()->update([
                        'filename' => $filename,
                        'bytes' => $file->getSize(),
                        'mime' => $file->getClientMimeType()
                    ]);
                }
                //수정하기전 게시글에 첨부파일이 없었던 경우   -> 첨부파일을 생성
                else{
                    \Log::info("작성");
                    $article->attachments()->create([
                        'filename' => $filename,
                        'bytes' => $file->getSize(),
                        'mime' => $file->getClientMimeType()
                    ]);
                }
                $file->move(attachments_path(), $filename);
            }
        }

        //store메서드에도 있음
        $article->tags()->sync($request->input('tags'));
        flash()->success('수정하신 내용을 저장했습니다.');
        return response()->json([], 204);
    }
    //게시판 삭제, 완료
    public function destroy(\App\Article $article)
    {   
        $this->authorize('delete', $article);
        flash()->success('게시판 삭제를 완료했습니다.');
        $article->delete();
        return response()->json([], 204);
    }
}