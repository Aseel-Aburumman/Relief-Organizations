<?php

namespace App\Http\Controllers\Post;

use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostStoreRequest;
use App\Http\Requests\PostUpdateRequest;
use App\Models\Post;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $search = $request->input('search');

        if ($user->hasRole('admin')) {
            $posts = Post::getAllPostsSearch($search);
        } elseif ($user->hasRole('organization')) {
            $organization = Organization::fetchOrganizationWithNeedsAndDonations(auth()->id());
            $posts = Post::fetchPostsWithImagesWityhoutLangSearch($organization->id, $search);
        }
        return view('dashboard.post.index', compact('posts'));
    }

    public function show($id)
    {
        $post = Post::getPostById($id);
        return view('dashboard.post.show', compact('post'));
    }

    // public function create()
    // {
    //     $languages = Language::all();

    //     if (Auth::user()->hasRole('organization')) {

    //         $organization = Organization::where('user_id', Auth::id())->first();

    //         return view('dashboard.post.create', compact('languages', 'organization'));
    //     } elseif (Auth::user()->hasRole('admin')) {

    //         $organizations = Organization::all();
    //         return view('dashboard.post.create', compact('languages', 'organizations'));
    //     }

    //     return redirect()->route('posts.index')->withErrors(__('You are not authorized to create posts.'));

    // }

    public function create()
{
    $languages = Language::all();

    if (Auth::user()->hasRole('organization')) {
        $organization = Organization::where('user_id', Auth::id())->first();


        return view('dashboard.post.create', compact('languages', 'organization'));
    }

    elseif (Auth::user()->hasRole('admin')) {
        $organizations = Organization::all();


        return view('dashboard.post.create', compact('languages', 'organizations'));
    }

    return redirect()->route('posts.index')->withErrors(__('You are not authorized to create posts.'));
}


    public function store(PostStoreRequest $request)
    {
        $data = $request->validated();

        if (Auth::user()->hasRole('organization')) {
            $organization = Organization::where('user_id', Auth::id())->first();
            if ($organization) {
                $data['organization_id'] = $organization->id;
            } else {
                return redirect()->back()->withErrors(__('Organization not found.'));
            }
        } elseif (Auth::user()->hasRole('admin')) {
            if (!isset($data['organization_id'])) {

                $defaultOrganization = Organization::first();
                if ($defaultOrganization) {
                    $data['organization_id'] = $defaultOrganization->id;
                } else {
                    return redirect()->back()->withErrors(__('No organization found to assign.'));
                }
            }
        } else {
            return redirect()->back()->withErrors(__('You are not authorized to create posts.'));
        }

        Post::createPost($data);
        return redirect()->route('posts.manage')->with('success', 'Post created successfully');
    }


    public function edit($id)
    {
        $post = Post::findOrFail($id);
        $languages = Language::all();
        return view('dashboard.post.edit', compact('post', 'languages'));
    }

    public function update(PostUpdateRequest $request, $id)
    {
        Post::updatePost($id, $request->validated());
        return redirect()->route('posts.manage')->with('success', 'Post updated successfully');
    }

    public function destroy($id)
    {
        Post::deletePost($id);
        return redirect()->route('posts.manage')->with('success', 'Post deleted successfully');
    }

    public function getOne($id)
    {
        $languageId = Language::getLanguageIdByLocale();

        $post = Post::with('images')->where('id', $id)->first();
        $organization = Organization::with(['userDetail' => function ($query) use ($languageId) {
            $query->orderByRaw("FIELD(language_id, ?, 1, 2)", [$languageId]);
        }])->find($post->organization_id);

        $posts = Post::fetchPostsWithImages($organization->id, $languageId);
        return view('organization.single-blog', compact('post', 'posts', 'organization'));
    }

    public function getAll($organization_id)
    {
        $languageId = Language::getLanguageIdByLocale();

        $organization = Organization::with(['userDetail' => function ($query) use ($languageId) {
            $query->orderByRaw("FIELD(language_id, ?, 1, 2)", [$languageId]);
        }])->find($organization_id);

        $posts = Post::with('images')
            ->where('lang_id', $languageId)
            ->where('organization_id', $organization_id)
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        return view('organization.blog', compact('posts', 'organization'));
    }
}

