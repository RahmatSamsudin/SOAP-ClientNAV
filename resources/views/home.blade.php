
<!DOCTYPE html>
<html lang="en">
	<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Exclude Item Management - Color Plate System</title>
        <!-- plugins:css -->
        <!-- CSS only -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

        <!-- JavaScript Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>
    </head>
    <body>
        <div class="container">
            <h4>CPS to NAV Manual Send</h4>
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{ Form::open(array('url' => '/send', 'id' => 'formSubmit')) }}
                <div class="row">
                    <div class="col-4">
                        <div class="form-floating mb-3">
                            <input id="inputTanggal" name="tanggal" type="date" class="form-control" placeholder="Select Date">
                            <label for="inputTanggal">Select Date</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-floating mb-3">
                            <select id="inputOutlet" name="outlet" class="form-control" placeholder="Select Store">

                                @foreach ($stores as $store)
                                    <option value="{{ $store->store_id }}">[{{ $store->locations->store_type }}] {{ $store->store_name }}</option>
                                @endforeach

                            </select>
                            <label for="inputOutlet">Select Store</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <input type="submit" name="Submit" value="Submit" class="btn btn-primary">
                    </div>
                </div>
            {{ Form::close() }}
        </div>
    </body>


</html>

