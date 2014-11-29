# Fileservice

A RESTful API that can be used to list directory contents, read files, upload
files, make directories, delete files and directories.

The original purpose of Fileservice was to allow PHP applications access a Linux
users' files (in users' home directories) on behalf of the user.

## Prerequisites

* Apache (Will run on other webservers with some extra work)
  * mod_rewrite
* PHP 5.4
  * CURL

## Setup

Put the `index.php` and `Fileservice.php` somewhere outside your public webroot,
where all users have permission to read.

For every user you want to use this Fileservice on, create a `public_html`
directory inside their home directory. Make an symbolic link to the `index.php`
file and copy the files `.htaccess` and `fileservice.conf.json` into
`public_html`.

Configure `.htaccess` and `fileservice.conf.json` with username and passwords.

Set up a `VirtualHost` for every users' home directory or configure `mod_userdir`
to make the Fileservice accessible for every user.

A PHP client is available in the `clients` folder.

## API

All methods except `GET` for files returns JSON.

If your client doesn't allow `DELETE` and `PUT` methods, or your proxy prohibits it,
the HTTP method can be set by the HTTP header `X-HTTP-Method-Override`.

### `GET`

Returns directory contents or the contents of a file.

**Request:**

```
GET /username/dir1/dir2/ HTTP/1.1
```

**Response:**

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
...

{
  "cwd": "\/dir1\/dir2\/",
  "directories": [
    {
      "name": "dir3",
      "mtime": 1412360850
    }
  ],
  "files": [
    {
      "name": "Mail.txt",
      "mtime": 1401642936,
      "size": 416,
      "type": "file"
    },
    {
      "name": "pic.jpg",
      "mtime": 1417200502,
      "size": 14504,
      "type": "file"
    }
  ]
}
```

**Request:**

```
GET /username/dir1/dir2/pic.jpg HTTP/1.1
```

**Response:**

```
HTTP/1.1 200 OK
Content-Type: image/jpeg; charset=binary

<BINARY CONTENT>
```

If the file or directory doesn't exist you get a `HTTP 404` error back.

### `PUT`

Uploads a new file/new file contents. Please be aware of PUT:ing a file that
already exists on given path **overwrites** the existing file!

**Request:**

```
PUT /username/dir1/dir2/newfile.txt HTTP/1.1

this is my new file content
```

**Response:**

```
HTTP/1.1 201 Created
```

### `POST`

Creates new directories, renames directories and files.

**Request:**

```
POST /username/dir1/dir2/ HTTP/1.1
Content-Type: application/x-www-form-urlencoded; charset=utf-8

name=my_new_dir
```

**Response:**

```
HTTP/1.1 201 Created
```

**Request:**

```
POST /username/dir1/dir2/my_new_dir/ HTTP/1.1
Content-Type: application/x-www-form-urlencoded; charset=utf-8

new_name=dir3
```

**Response:**

```
HTTP/1.1 200 OK
```

**Request:**

```
POST /username/dir1/dir2/newfile.txt HTTP/1.1
Content-Type: application/x-www-form-urlencoded; charset=utf-8

new_name=newfile2.txt
```

**Response:**

```
HTTP/1.1 200 OK
```

### `DELETE`

Deletes directories and files. Trying to delete a non-empty directory will fail.

**Request:**

```
DELETE /username/dir1/dir2/newfile.txt HTTP/1.1
```

**Response:**

```
HTTP/1.1 204 No content
```

**Request:**

```
DELETE /username/dir1/dir2/ HTTP/1.1
```

**Response:**

```
HTTP/1.1 204 No content
```

## Bugs and enhancements

I'm happy to know if you find any bugs, if you do, report it in Github issues.

## License

MIT