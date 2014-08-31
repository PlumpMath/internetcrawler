<?php

libxml_use_internal_errors(true);

function getElementsByClassName($DOMDocument, $ClassName)
{
    $Elements = $DOMDocument -> getElementsByTagName("*");
    $Matched = array();
 
    foreach($Elements as $node)
    {
        if( ! $node -> hasAttributes())
            continue;
 
        $classAttribute = $node -> attributes -> getNamedItem('class');
 
        if( ! $classAttribute)
            continue;
 
        $classes = explode(' ', $classAttribute -> nodeValue);
 
        if(in_array($ClassName, $classes))
            $Matched[] = $node;
    }
 
    return $Matched;
}

class Crawler
{
  protected $url;
  protected $path;
  protected $page;

  public function __construct($url, $path)
  {
    $this->url = $url;
    $this->path = $path;
  }

  public function url($name, $options)
  {
    return $this->url . call_user_func_array($this->path[$name], $options);
  }

  public function download($file, $as)
  {
    $downloaded = file_get_contents($file);

    return file_put_contents($as, $downloaded);
  }

  public function __call($name, $arguments)
  {
    foreach (static::pattern() as $key => $pattern) {
      if (preg_match($pattern, $name)) {
        switch ($key) {
          case 'from':
            $what = strtolower(substr($name, 4));
            return $this->from($what, $arguments);
            break;
        }
      }
    }
  }

  public function from($name, $options)
  {
    $html = file_get_contents($this->url($name, (array) $options));
    $document = DOMDocument::loadHTML($html);
    return $document;
  }

  public function get($attr, $element)
  {
  }

  public function pattern()
  {
    return [
      'from'  => '/from[A-Z][a-z]+/',
      'get'   => '/get[A-Z][a-z]+/',
    ];
  }
}

$baseURL = 'https://thecreatorsproject.vice.com/';

$Crawler = new Crawler($baseURL, [
  'author'  => function ($id, $page) {
    return "tcp/tcpArticle/author/author_id/{$id}/Article_page/{$page}";
  },
  'link' => function ($name) {
    return $name;
  }
]);

$page = 1;
$titles = array();
$json = array();
$continue = true;

while ($continue == true) {
  echo "Current page: $page\n";
  $author = $Crawler->fromAuthor('beckettmufson', $page);
  $container = $author->getElementById('yw0');
  $images = getElementsByClassName($container, 'story_thumbnail');
  $articles = getElementsByClassName($container, 'details');

  $article = array();

  for ($i = 0; $i < count($articles); $i++) {
    $title = trim($articles[$i]->getElementsByTagName('h2')->item(0)->textContent);
    $desc = trim($articles[$i]->getElementsByTagName('p')->item(0)->textContent);
    $img = stripslashes($images[$i]->getElementsByTagName('img')->item(0)->attributes->getNamedItem('src')->textContent);
    $rellink = $images[$i]->attributes->getNamedItem('href')->textContent;
    $link = stripslashes($baseURL . $rellink);

    $content = $Crawler->fromLink($rellink);
    $main = $content->getElementById('main');
    $aa = getElementsByClassName($main, 'a_column')[0];
    $cn = getElementsByClassName($aa, 'article_content')[0];
    $ps = $cn->getElementsByTagName('p');

    $article['paragraphs'] = array();

    for ($k = 1; $k < 3; $k++) {
      array_push($article['paragraphs'], trim($ps->item($k)->textContent));
    }

    $article['link'] = $link;
    $article['desc'] = $desc;
    $article['img'] = $img;

    if (array_search($title, $titles) == false) {
      array_push($titles, $title);
      $article['title'] = $title;
      array_push($json, $article);
    } else {
      echo "REACHED";
      $continue = false;
      break;
    }

    echo "Title: $title\n";
  }

  $page++;
}

file_put_contents('out.json', json_encode($json));
